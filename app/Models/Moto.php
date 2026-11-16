<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Moto extends Model
{
    protected $fillable = [
        'reference',
        'modele_id',
        'marque',
        'modele',
        'annee',
        'couleur',
        'numero_chassis',
        'kilometrage',
        'prix_achat',
        'prix_vente',
        'statut',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'prix_achat' => 'decimal:2',
            'prix_vente' => 'decimal:2',
            'kilometrage' => 'integer',
        ];
    }

    // Le modèle du catalogue auquel cette fiche est rattachée
    public function catalogue(): BelongsTo
    {
        return $this->belongsTo(Modele::class, 'modele_id');
    }

    public function venteDetails(): HasMany
    {
        return $this->hasMany(VenteDetail::class);
    }

    // Marge prévisionnelle = prix de vente - prix d'achat (null tant qu'il n'y a pas de prix de vente)
    public function getMargeAttribute(): ?float
    {
        if ($this->prix_vente === null) {
            return null;
        }

        return (float) $this->prix_vente - (float) $this->prix_achat;
    }

    public function estDisponible(): bool
    {
        return $this->statut === 'disponible';
    }

    public function scopeDisponible(Builder $query): Builder
    {
        return $query->where('statut', 'disponible');
    }
}