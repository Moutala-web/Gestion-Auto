<?php

namespace App\Services;

use App\Models\Modele;
use App\Models\Moto;
use App\Models\Piece;
use App\Models\Vehicule;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ArrivageService
{
    // Au-delà, on découpe en plusieurs lignes : évite une faute de frappe (500 au lieu de 5)
    private const MAX_FICHES_PAR_LIGNE = 50;

    public function __construct(private AchatService $achats)
    {
    }

    /**
     * Enregistre un achat : crée les fiches (véhicules, motos), augmente le stock (pièces, articles divers)
     * et, sauf s'il s'agit de stock déjà payé, enregistre chaque achat avec sa sortie de caisse.
     * C'est la seule façon de faire entrer du stock. Tout est fait dans une seule transaction.
     *
     * @return array{vehicules:int, motos:int, pieces:int, montant:float, references:array, en_caisse:bool}
     */
    public function creer(array $data, int $userId): array
    {
        $enCaisse = (bool) ($data['enregistrer_en_caisse'] ?? true);
        $mode = $data['mode_paiement'] ?? 'especes';

        if ($enCaisse && $mode === 'orange_money' && blank($data['reference_transaction'] ?? null)) {
            $this->erreur('reference_transaction', 'La référence de la transaction Orange Money est obligatoire.');
        }

        return DB::transaction(function () use ($data, $userId, $enCaisse, $mode) {
            $resume = [
                'vehicules' => 0,
                'motos' => 0,
                'pieces' => 0,
                'montant' => 0.0,
                'references' => [],
                'en_caisse' => $enCaisse,
            ];

            $date = Carbon::parse($data['date_achat']);
            $note = trim(
                'Achat du ' . $date->format('d/m/Y')
                . (filled($data['fournisseur'] ?? null) ? ' — ' . $data['fournisseur'] : '')
                . (filled($data['notes'] ?? null) ? "\n" . $data['notes'] : '')
            );

            // Éléments communs à chaque achat enregistré
            $achat = [
                'fournisseur' => $data['fournisseur'] ?? null,
                'mode_paiement' => $mode,
                'reference_transaction' => $data['reference_transaction'] ?? null,
                'date_achat' => $data['date_achat'],
                'notes' => $note,
            ];

            foreach (array_values($data['lignes']) as $i => $ligne) {
                $numero = $i + 1;
                $type = $ligne['type'];
                $quantite = (int) $ligne['quantite'];
                $prix = round((float) $ligne['prix_unitaire'], 2);

                if ($type === 'piece') {
                    $modele = Modele::whereIn('type', ['piece', 'divers'])->find($ligne['modele_id'] ?? null);

                    if (! $modele) {
                        $this->erreur('lignes', "Ligne {$numero} : choisis une pièce ou un article du catalogue.");
                    }

                    $this->recevoirStock($modele, $quantite, $prix, $achat, $enCaisse, $userId);
                    $resume['pieces'] += $quantite;
                } else {
                    if ($quantite > self::MAX_FICHES_PAR_LIGNE) {
                        $this->erreur('lignes', "Ligne {$numero} : {$quantite} fiches d'un coup, c'est beaucoup. Maximum " . self::MAX_FICHES_PAR_LIGNE . ' par ligne : sépare en plusieurs lignes.');
                    }

                    $modele = Modele::where('type', $type === 'vehicule' ? 'voiture' : 'moto')
                        ->find($ligne['modele_id'] ?? null);

                    if (! $modele) {
                        $this->erreur('lignes', "Ligne {$numero} : choisis un modèle du catalogue.");
                    }

                    for ($n = 0; $n < $quantite; $n++) {
                        $fiche = $this->creerFiche($type, $modele, $ligne, $prix);
                        $resume['references'][] = $fiche->reference;

                        if ($enCaisse) {
                            $this->achats->creer($achat + [
                                'type_article' => $type,
                                'article_id' => $fiche->id,
                                'quantite' => 1,
                                'prix_unitaire' => $prix,
                            ], $userId);
                        }
                    }

                    $resume[$type === 'vehicule' ? 'vehicules' : 'motos'] += $quantite;
                }

                $resume['montant'] += $prix * $quantite;
            }

            return $resume;
        });
    }

    /** Une pièce ou un article reçu : la fiche de stock est créée au premier achat, puis le stock augmente. */
    private function recevoirStock(Modele $modele, int $quantite, float $prix, array $achat, bool $enCaisse, int $userId): void
    {
        $piece = Piece::where('modele_id', $modele->id)->first() ?? $this->creerPiece($modele, $prix);

        if ($enCaisse) {
            $this->achats->creer($achat + [
                'type_article' => 'piece',
                'article_id' => $piece->id,
                'quantite' => $quantite,
                'prix_unitaire' => $prix,
            ], $userId);

            return;
        }

        // Stock déjà payé : on augmente le stock sans toucher à la caisse
        $verrouillee = Piece::whereKey($piece->id)->lockForUpdate()->firstOrFail();
        $verrouillee->appliquerReception($quantite, $prix);

        $verrouillee->mouvements()->create([
            'type' => 'entree',
            'quantite' => $quantite,
            'prix_achat' => $prix,
            'motif' => 'Réception (déjà payée, hors caisse)',
            'user_id' => $userId,
        ]);
    }

    /** Crée la fiche de stock d'une pièce ou d'un article divers à partir de sa définition dans le catalogue. */
    private function creerPiece(Modele $modele, float $prix): Piece
    {
        $piece = Piece::create([
            'reference' => 'TMP-' . Str::uuid(),
            'modele_id' => $modele->id,
            'famille' => $modele->type,
            'designation' => $modele->modele,
            'categorie' => $modele->categorie,
            'marque' => filled($modele->marque) ? $modele->marque : null,
            'prix_achat' => $prix,
            'prix_vente' => $modele->prix_vente_indicatif ?? 0,
            'quantite_stock' => 0,
            'seuil_alerte' => $modele->seuil_alerte ?? 0,
        ]);

        $reference = sprintf($modele->type === 'divers' ? 'AD-%05d' : 'PC-%05d', $piece->id);

        if (Piece::where('reference', $reference)->exists()) {
            $reference .= '-' . strtoupper(Str::random(3));
        }

        $piece->update(['reference' => $reference]);

        return $piece;
    }

    /** Crée la fiche d'un véhicule ou d'une moto, avec une référence automatique sans doublon. */
    private function creerFiche(string $type, Modele $modele, array $ligne, float $prix): Model
    {
        $classe = $type === 'vehicule' ? Vehicule::class : Moto::class;
        $prefixe = $type === 'vehicule' ? 'VH' : 'MO';

        $fiche = $classe::create([
            'reference' => 'TMP-' . Str::uuid(),
            'modele_id' => $modele->id,
            'marque' => $modele->marque,
            'modele' => $modele->modele,
            'annee' => filled($ligne['annee'] ?? null) ? (int) $ligne['annee'] : null,
            'couleur' => filled($ligne['couleur'] ?? null) ? $ligne['couleur'] : null,
            'kilometrage' => 0,
            'prix_achat' => $prix,
            'prix_vente' => $modele->prix_vente_indicatif,
            'statut' => 'disponible',
        ]);

        // La référence vient de l'identifiant : unique même si deux achats sont saisis en même temps
        $reference = sprintf('%s-%05d', $prefixe, $fiche->id);

        if ($classe::where('reference', $reference)->exists()) {
            $reference .= '-' . strtoupper(Str::random(3));
        }

        $fiche->update(['reference' => $reference]);

        return $fiche;
    }

    private function erreur(string $champ, string $message): never
    {
        throw ValidationException::withMessages([$champ => $message]);
    }
}