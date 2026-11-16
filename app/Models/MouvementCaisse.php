<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MouvementCaisse extends Model
{
    // Laravel devinerait « mouvement_caisses » : on indique le vrai nom de la table
    protected $table = 'mouvements_caisse';

    // La table n'a pas de colonne updated_at : un mouvement ne se modifie jamais
    public const UPDATED_AT = null;

    // Entrées qui ne sont PAS du chiffre d'affaires : argent détenu pour le client,
    // ou contrepartie d'une dépense, d'un frais ou d'un achat annulé
    public const CATEGORIES_HORS_CA = [
        'caution',
        'remboursement_caution',
        'annulation_frais',
        'annulation_depense',
        'annulation_achat',
    ];

    // Sorties de caisse qui annulent une recette (vente ou location annulée)
    public const CATEGORIES_ANNULATION = ['annulation_vente', 'annulation_location'];

    // Les dépenses regroupent trois natures. Chacune est associée à sa contrepartie d'annulation.
    public const NATURES_DEPENSES = [
        'achat' => 'annulation_achat',
        'frais_vehicule' => 'annulation_frais',
        'depense' => 'annulation_depense',
    ];

    // Libellés affichés dans le journal
    public const LIBELLES = [
        'vente' => 'Vente',
        'location' => 'Location',
        'caution' => 'Caution reçue',
        'remboursement_caution' => 'Caution remboursée',
        'annulation_vente' => 'Vente annulée',
        'annulation_location' => 'Location annulée',
        'frais_vehicule' => 'Frais de véhicule',
        'annulation_frais' => 'Frais annulé',
        'depense' => 'Dépense',
        'annulation_depense' => 'Dépense annulée',
        'achat' => 'Achat',
        'annulation_achat' => 'Achat annulé',
    ];

    protected $fillable = [
        'user_id',
        'type',
        'categorie',
        'montant',
        'mode_paiement',
        'reference_type',
        'reference_id',
        'date_operation',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'montant' => 'decimal:2',
            'date_operation' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ---------- Scopes ----------

    public function scopeEntrees(Builder $query): Builder
    {
        return $query->where('type', 'entree');
    }

    public function scopeSorties(Builder $query): Builder
    {
        return $query->where('type', 'sortie');
    }

    // Entrées qui comptent dans le chiffre d'affaires
    public function scopeRecettes(Builder $query): Builder
    {
        return $query->where('type', 'entree')
            ->whereNotIn('categorie', self::CATEGORIES_HORS_CA);
    }

    // Limite à une période (dates au format AAAA-MM-JJ, bornes incluses, null = sans limite)
    public function scopeEntre(Builder $query, ?string $du = null, ?string $au = null): Builder
    {
        return $query
            ->when($du, fn ($q) => $q->where('date_operation', '>=', Carbon::parse($du)->startOfDay()))
            ->when($au, fn ($q) => $q->where('date_operation', '<=', Carbon::parse($au)->endOfDay()));
    }

    // ---------- Calculs ----------

    // Solde de la caisse = somme des entrées - somme des sorties (caution comprise : c'est de l'argent réellement détenu)
    public static function solde(?string $modePaiement = null): float
    {
        $query = static::query();

        if ($modePaiement !== null) {
            $query->where('mode_paiement', $modePaiement);
        }

        $entrees = (float) (clone $query)->where('type', 'entree')->sum('montant');
        $sorties = (float) (clone $query)->where('type', 'sortie')->sum('montant');

        return $entrees - $sorties;
    }

    // Chiffre d'affaires = recettes hors caution, moins les ventes et locations annulées
    public static function chiffreAffaires(?string $du = null, ?string $au = null): float
    {
        $recettes = (float) static::recettes()->entre($du, $au)->sum('montant');

        $annulations = (float) static::sorties()
            ->whereIn('categorie', self::CATEGORIES_ANNULATION)
            ->entre($du, $au)
            ->sum('montant');

        return $recettes - $annulations;
    }

    /**
     * Dépenses nettes d'une période, par nature : achats, frais de véhicules et dépenses de fonctionnement.
     * Chaque total est diminué de ses annulations. Clés : achat, frais_vehicule, depense, total.
     */
    public static function totauxDepenses(?string $du = null, ?string $au = null): array
    {
        $sorties = static::sorties()
            ->whereIn('categorie', array_keys(self::NATURES_DEPENSES))
            ->entre($du, $au)
            ->selectRaw('categorie, SUM(montant) as total')
            ->groupBy('categorie')
            ->pluck('total', 'categorie');

        $contreparties = static::entrees()
            ->whereIn('categorie', array_values(self::NATURES_DEPENSES))
            ->entre($du, $au)
            ->selectRaw('categorie, SUM(montant) as total')
            ->groupBy('categorie')
            ->pluck('total', 'categorie');

        $totaux = [];
        $somme = 0.0;

        foreach (self::NATURES_DEPENSES as $nature => $annulation) {
            $net = (float) ($sorties[$nature] ?? 0) - (float) ($contreparties[$annulation] ?? 0);
            $totaux[$nature] = $net;
            $somme += $net;
        }

        $totaux['total'] = $somme;

        return $totaux;
    }
}