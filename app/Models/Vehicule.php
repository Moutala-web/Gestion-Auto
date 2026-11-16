<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vehicule extends Model
{
    protected $fillable = [
        'reference',
        'modele_id',
        'marque',
        'modele',
        'annee',
        'immatriculation',
        'numero_chassis',
        'couleur',
        'kilometrage',
        'description',
        'prix_achat',
        'prix_vente',
        'statut',
    ];

    protected function casts(): array
    {
        return [
            'prix_achat' => 'decimal:2',
            'prix_vente' => 'decimal:2',
            'kilometrage' => 'integer',
        ];
    }

    // ---------- Relations ----------

    // Le modèle du catalogue auquel cette fiche est rattachée
    // (« modele » est déjà le nom d'une colonne : la relation s'appelle donc « catalogue »)
    public function catalogue(): BelongsTo
    {
        return $this->belongsTo(Modele::class, 'modele_id');
    }

    public function frais(): HasMany
    {
        return $this->hasMany(VehiculeFrais::class);
    }

    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }

    public function venteDetails(): HasMany
    {
        return $this->hasMany(VenteDetail::class);
    }

    // ---------- Calculs (jamais stockés en base) ----------

    // Somme des frais. Si on a chargé le véhicule avec withSum('frais', 'montant'),
    // on réutilise ce résultat au lieu de refaire une requête.
    public function getTotalFraisAttribute(): float
    {
        if (array_key_exists('frais_sum_montant', $this->attributes)) {
            return (float) $this->attributes['frais_sum_montant'];
        }

        return (float) $this->frais()->sum('montant');
    }

    // Coût total = prix d'achat + ensemble des frais
    public function getCoutTotalAttribute(): float
    {
        return (float) $this->prix_achat + $this->total_frais;
    }

    // Marge = prix de vente - coût total (null tant qu'aucun prix de vente n'est défini)
    public function getMargeAttribute(): ?float
    {
        if ($this->prix_vente === null) {
            return null;
        }

        return (float) $this->prix_vente - $this->cout_total;
    }

    // ---------- Aides ----------

    public function estDisponible(): bool
    {
        return $this->statut === 'disponible';
    }

    // Permet d'écrire Vehicule::disponible()->get()
    public function scopeDisponible(Builder $query): Builder
    {
        return $query->where('statut', 'disponible');
    }
}