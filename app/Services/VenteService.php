<?php

namespace App\Services;

use App\Models\Location;
use App\Models\Moto;
use App\Models\Piece;
use App\Models\Recu;
use App\Models\Vehicule;
use App\Models\Vente;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class VenteService
{
    public function __construct(private CaisseService $caisse)
    {
    }

    /**
     * Enregistre une vente au comptant. Tout est fait dans une seule transaction :
     * si une étape échoue, rien n'est enregistré.
     *
     * @param array{client_id:int, mode_paiement:string, reference_transaction?:?string, remise?:mixed, notes?:?string, lignes:array} $data
     */
    public function creer(array $data, int $userId): Vente
    {
        return DB::transaction(function () use ($data, $userId) {
            // 1. Verrouiller et contrôler tous les articles (disponibilité, stock)
            $lignes = $this->preparerLignes($data['lignes']);

            // 2. Totaux
            $sousTotal = round(array_sum(array_column($lignes, 'total')), 2);
            $remise = round((float) ($data['remise'] ?? 0), 2);

            if ($remise > $sousTotal) {
                $this->erreur('remise', 'La remise ne peut pas dépasser le sous-total.');
            }

            $total = round($sousTotal - $remise, 2);

            if ($total <= 0) {
                $this->erreur('remise', 'Le total de la vente doit être supérieur à zéro.');
            }

            // 3. La vente (numéro provisoire, remplacé par un numéro séquentiel sans risque de doublon)
            $vente = Vente::create([
                'numero' => 'TMP-' . Str::uuid(),
                'client_id' => $data['client_id'],
                'user_id' => $userId,
                'date_vente' => now(),
                'sous_total' => $sousTotal,
                'remise' => $remise,
                'total' => $total,
                'statut' => 'validee',
                'notes' => $data['notes'] ?? null,
            ]);
            $vente->update(['numero' => sprintf('V-%05d', $vente->id)]);

            // 4. Lignes + effets sur le stock et les statuts
            foreach ($lignes as $ligne) {
                $article = $ligne['article']; // null pour un service

                $vente->details()->create([
                    'type_article' => $ligne['type'],
                    'vehicule_id' => $ligne['type'] === 'vehicule' ? $article->id : null,
                    'moto_id' => $ligne['type'] === 'moto' ? $article->id : null,
                    'piece_id' => $ligne['type'] === 'piece' ? $article->id : null,
                    'designation' => $ligne['designation'],
                    'quantite' => $ligne['quantite'],
                    'prix_unitaire' => $ligne['prix'],
                    'total' => $ligne['total'],
                ]);

                if ($ligne['type'] === 'piece') {
                    $article->decrement('quantite_stock', $ligne['quantite']);

                    $article->mouvements()->create([
                        'type' => 'sortie',
                        'quantite' => $ligne['quantite'],
                        'motif' => 'Vente',
                        'reference_operation' => $vente->numero,
                        'user_id' => $userId,
                    ]);
                } elseif (in_array($ligne['type'], ['vehicule', 'moto'], true)) {
                    $article->update(['statut' => 'vendu']);
                }
                // Un service n'a ni stock ni statut à mettre à jour
            }

            // 5. Paiement (vente au comptant : payée en totalité)
            $paiement = $vente->paiements()->create([
                'type' => 'vente',
                'mode' => $data['mode_paiement'],
                'montant' => $total,
                'reference_transaction' => $data['reference_transaction'] ?? null,
                'date_paiement' => now(),
                'statut' => 'valide',
            ]);

            // 6. Recette en caisse
            $this->caisse->entree(
                categorie: 'vente',
                montant: $total,
                mode: $data['mode_paiement'],
                userId: $userId,
                refType: 'vente',
                refId: $vente->id,
                description: 'Vente ' . $vente->numero,
            );

            // 7. Reçu
            $recu = Recu::create([
                'numero' => 'TMP-' . Str::uuid(),
                'vente_id' => $vente->id,
                'paiement_id' => $paiement->id,
                'date_generation' => now(),
            ]);
            $recu->update(['numero' => sprintf('R-%05d', $recu->id)]);

            return $vente;
        });
    }

    /**
     * Annule une vente : les articles redeviennent disponibles, le stock est remis,
     * le paiement est annulé et la sortie de caisse correspondante est enregistrée.
     */
    public function annuler(Vente $vente, string $motif, int $userId): Vente
    {
        return DB::transaction(function () use ($vente, $motif, $userId) {
            $vente = Vente::whereKey($vente->id)->lockForUpdate()->firstOrFail();

            if ($vente->statut !== 'validee') {
                $this->erreur('motif', 'Cette vente est déjà annulée.');
            }

            foreach ($vente->details()->get() as $detail) {
                if ($detail->type_article === 'vehicule') {
                    Vehicule::whereKey($detail->vehicule_id)->lockForUpdate()->first()?->update(['statut' => 'disponible']);
                } elseif ($detail->type_article === 'moto') {
                    Moto::whereKey($detail->moto_id)->lockForUpdate()->first()?->update(['statut' => 'disponible']);
                } elseif ($detail->type_article === 'piece') {
                    $piece = Piece::whereKey($detail->piece_id)->lockForUpdate()->first();

                    if ($piece) {
                        $piece->increment('quantite_stock', $detail->quantite);

                        $piece->mouvements()->create([
                            'type' => 'entree',
                            'quantite' => $detail->quantite,
                            'prix_achat' => $piece->prix_achat,
                            'motif' => 'Annulation de la vente',
                            'reference_operation' => $vente->numero,
                            'user_id' => $userId,
                        ]);
                    }
                }
                // Un service n'a rien à remettre
            }

            // Les paiements sont annulés, jamais supprimés
            foreach ($vente->paiements()->where('statut', 'valide')->get() as $paiement) {
                $paiement->update(['statut' => 'annule']);

                $this->caisse->sortie(
                    categorie: 'annulation_vente',
                    montant: (float) $paiement->montant,
                    mode: $paiement->mode,
                    userId: $userId,
                    refType: 'vente',
                    refId: $vente->id,
                    description: 'Annulation de la vente ' . $vente->numero,
                );
            }

            $vente->update([
                'statut' => 'annulee',
                'notes' => trim(($vente->notes ? $vente->notes . "\n" : '') . 'Annulée : ' . $motif),
            ]);

            return $vente;
        });
    }

    /**
     * Verrouille chaque article (dans un ordre fixe pour éviter les blocages entre deux ventes
     * simultanées) puis vérifie qu'il est vendable. Les services n'ont pas d'article à verrouiller.
     */
    private function preparerLignes(array $brutes): array
    {
        $brutes = collect($brutes)
            ->sortBy(fn ($l) => $l['type'] . '-' . str_pad((string) ($l['id'] ?? ''), 10, '0', STR_PAD_LEFT))
            ->values();

        $vus = [];
        $lignes = [];

        foreach ($brutes as $ligne) {
            $type = $ligne['type'];
            $quantite = (int) $ligne['quantite'];
            $prix = round((float) $ligne['prix_unitaire'], 2);

            // Service : un nom, une quantité et un prix suffisent
            if ($type === 'service') {
                $designation = trim((string) ($ligne['designation'] ?? ''));

                if ($designation === '') {
                    $this->erreur('lignes', 'Indique le nom du service pour chaque ligne de service.');
                }

                $lignes[] = [
                    'type' => 'service',
                    'article' => null,
                    'designation' => $designation,
                    'quantite' => $quantite,
                    'prix' => $prix,
                    'total' => round($prix * $quantite, 2),
                ];

                continue;
            }

            if (empty($ligne['id'])) {
                $this->erreur('lignes', 'Choisis un article pour chaque ligne.');
            }

            $id = (int) $ligne['id'];

            if (isset($vus[$type . '-' . $id])) {
                $this->erreur('lignes', 'Un même article ne peut pas être ajouté deux fois.');
            }
            $vus[$type . '-' . $id] = true;

            $article = match ($type) {
                'vehicule' => Vehicule::whereKey($id)->lockForUpdate()->first(),
                'moto' => Moto::whereKey($id)->lockForUpdate()->first(),
                'piece' => Piece::whereKey($id)->lockForUpdate()->first(),
            };

            if (! $article) {
                $this->erreur('lignes', 'Un des articles sélectionnés n\'existe plus.');
            }

            if ($type === 'piece') {
                if ($article->quantite_stock < $quantite) {
                    $this->erreur('lignes', "Stock insuffisant pour « {$article->designation} » : il reste {$article->quantite_stock} en stock.");
                }

                $designation = $article->designation;
            } else {
                $nom = "{$article->marque} {$article->modele} ({$article->reference})";

                // Un véhicule ou une moto est un objet unique : jamais vendu deux fois
                if (! in_array($article->statut, ['disponible', 'reserve'], true)) {
                    $this->erreur('lignes', "« {$nom} » n'est plus disponible (statut : {$article->statut}).");
                }

                // Un véhicule qui a une location réservée ou en cours ne peut pas être vendu
                if ($type === 'vehicule'
                    && Location::where('vehicule_id', $article->id)->whereIn('statut', ['reservee', 'en_cours'])->exists()) {
                    $this->erreur('lignes', "« {$nom} » a une location réservée ou en cours : il ne peut pas être vendu.");
                }

                $quantite = 1;
                $designation = $nom;
            }

            $lignes[] = [
                'type' => $type,
                'article' => $article,
                'designation' => $designation,
                'quantite' => $quantite,
                'prix' => $prix,
                'total' => round($prix * $quantite, 2),
            ];
        }

        return $lignes;
    }

    private function erreur(string $champ, string $message): never
    {
        throw ValidationException::withMessages([$champ => $message]);
    }
}