<?php

namespace App\Services;

use App\Models\Achat;
use App\Models\Moto;
use App\Models\Piece;
use App\Models\Vehicule;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AchatService
{
    public function __construct(private CaisseService $caisse)
    {
    }

    /**
     * Enregistre l'achat d'un article déjà créé (fiche véhicule, fiche moto ou pièce) :
     * sortie de caisse et, pour une pièce, entrée de stock au prix moyen pondéré. Tout ou rien.
     * Cette méthode est appelée par l'arrivage : on n'achète jamais depuis l'écran un article qui n'existe pas encore.
     *
     * @param array{type_article:string, article_id:int, quantite?:mixed, prix_unitaire:mixed, fournisseur?:?string, mode_paiement:string, reference_transaction?:?string, date_achat:string, notes?:?string} $data
     */
    public function creer(array $data, int $userId): Achat
    {
        return DB::transaction(function () use ($data, $userId) {
            $type = $data['type_article'];
            $id = (int) $data['article_id'];

            // Le verrou empêche deux achats simultanés du même véhicule
            $article = match ($type) {
                'vehicule' => Vehicule::whereKey($id)->lockForUpdate()->first(),
                'moto' => Moto::whereKey($id)->lockForUpdate()->first(),
                'piece' => Piece::whereKey($id)->lockForUpdate()->first(),
            };

            if (! $article) {
                $this->erreur('article_id', 'L\'article sélectionné n\'existe plus.');
            }

            // Un véhicule ou une moto est unique : on ne l'achète qu'une fois
            if ($type !== 'piece' && Achat::valides()->where($type . '_id', $id)->exists()) {
                $this->erreur('article_id', 'L\'achat de cet article est déjà enregistré.');
            }

            $quantite = $type === 'piece' ? (int) $data['quantite'] : 1;
            $prix = round((float) $data['prix_unitaire'], 2);
            $montant = round($prix * $quantite, 2);
            $date = Carbon::parse($data['date_achat'])->setTimeFrom(now());

            $achat = Achat::create([
                'type_article' => $type,
                'vehicule_id' => $type === 'vehicule' ? $id : null,
                'moto_id' => $type === 'moto' ? $id : null,
                'piece_id' => $type === 'piece' ? $id : null,
                'fournisseur' => $data['fournisseur'] ?? null,
                'quantite' => $quantite,
                'prix_unitaire' => $prix,
                'montant' => $montant,
                'mode_paiement' => $data['mode_paiement'],
                'reference_transaction' => $data['reference_transaction'] ?? null,
                'date_achat' => $date,
                'user_id' => $userId,
                'notes' => $data['notes'] ?? null,
                'statut' => 'valide',
            ]);

            if ($type === 'piece') {
                // Stock augmenté, prix d'achat de la pièce recalculé (prix moyen pondéré)
                $article->appliquerReception($quantite, $prix);

                $article->mouvements()->create([
                    'type' => 'entree',
                    'quantite' => $quantite,
                    'prix_achat' => $prix,
                    'motif' => 'Achat' . (! empty($data['fournisseur']) ? ' — ' . $data['fournisseur'] : ''),
                    'reference_operation' => $achat->numero,
                    'user_id' => $userId,
                ]);
            } else {
                // Le prix réellement payé est le prix d'achat de la fiche : la marge reste exacte
                $article->update(['prix_achat' => $prix]);
            }

            $this->caisse->sortie(
                categorie: 'achat',
                montant: $montant,
                mode: $data['mode_paiement'],
                userId: $userId,
                refType: 'achat',
                refId: $achat->id,
                description: 'Achat ' . $achat->numero . ' : ' . $achat->fresh()->libelle_article,
                date: $date,
            );

            return $achat;
        });
    }

    /**
     * Annule un achat de pièces : la caisse reçoit la contrepartie et le stock est retiré
     * (refusé si les pièces ont déjà été vendues). L'achat d'un véhicule ou d'une moto ne s'annule pas :
     * sa fiche est dans le stock.
     */
    public function annuler(Achat $achat, string $motif, int $userId): Achat
    {
        return DB::transaction(function () use ($achat, $motif, $userId) {
            $achat = Achat::whereKey($achat->id)->lockForUpdate()->firstOrFail();

            if ($achat->statut !== 'valide') {
                $this->erreur('motif', 'Cet achat est déjà annulé.');
            }

            if ($achat->type_article !== 'piece') {
                $this->erreur('motif', 'L\'achat d\'un véhicule ou d\'une moto ne peut pas être annulé : sa fiche est dans le stock.');
            }

            $piece = Piece::whereKey($achat->piece_id)->lockForUpdate()->first();

            if ($piece) {
                if ($piece->quantite_stock < $achat->quantite) {
                    $this->erreur('motif', "Annulation impossible : {$achat->quantite} pièce(s) ont été achetées mais il n'en reste que {$piece->quantite_stock} en stock (le reste a déjà été vendu ou sorti).");
                }

                $piece->decrement('quantite_stock', $achat->quantite);

                $piece->mouvements()->create([
                    'type' => 'sortie',
                    'quantite' => $achat->quantite,
                    'motif' => 'Annulation de l\'achat',
                    'reference_operation' => $achat->numero,
                    'user_id' => $userId,
                ]);
            }

            $achat->update([
                'statut' => 'annule',
                'motif_annulation' => $motif,
            ]);

            $this->caisse->entree(
                categorie: 'annulation_achat',
                montant: (float) $achat->montant,
                mode: $achat->mode_paiement,
                userId: $userId,
                refType: 'achat',
                refId: $achat->id,
                description: 'Annulation de l\'achat ' . $achat->numero,
            );

            return $achat;
        });
    }

    private function erreur(string $champ, string $message): never
    {
        throw ValidationException::withMessages([$champ => $message]);
    }
}