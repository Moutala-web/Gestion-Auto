<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Modele extends Model
{
    // Le catalogue définit tout ce qui peut être acheté : le stock n'entre que par un achat
    public const TYPES = [
        'voiture' => 'Voiture',
        'moto' => 'Moto',
        'piece' => 'Pièce détachée',
        'divers' => 'Article divers',
    ];

    public const CARBURANTS = [
        'essence' => 'Essence',
        'diesel' => 'Diesel',
        'hybride' => 'Hybride',
        'electrique' => 'Électrique',
    ];

    public const BOITES = [
        'manuelle' => 'Manuelle',
        'automatique' => 'Automatique',
    ];

    protected $fillable = [
        'type',
        'marque',
        'modele',
        'categorie',
        'prix_vente_indicatif',
        'seuil_alerte',
        'cylindree',
        'carburant',
        'boite',
    ];

    protected function casts(): array
    {
        return [
            'prix_vente_indicatif' => 'decimal:2',
            'seuil_alerte' => 'integer',
            'cylindree' => 'integer',
        ];
    }

    public function vehicules(): HasMany
    {
        return $this->hasMany(Vehicule::class, 'modele_id');
    }

    public function motos(): HasMany
    {
        return $this->hasMany(Moto::class, 'modele_id');
    }

    public function pieces(): HasMany
    {
        return $this->hasMany(Piece::class, 'modele_id');
    }

    // Pour une pièce, « modele » est sa désignation et la marque peut être vide
    public function getNomCompletAttribute(): string
    {
        return trim("{$this->marque} {$this->modele}");
    }

    // Voiture ou moto : une fiche par unité. Sinon pièce ou article divers : géré par quantité.
    public function estFiche(): bool
    {
        return in_array($this->type, ['voiture', 'moto'], true);
    }
}