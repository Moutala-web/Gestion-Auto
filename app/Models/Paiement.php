<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Paiement extends Model
{
    // La table n'a pas de colonne updated_at
    public const UPDATED_AT = null;

    protected $fillable = [
        'vente_id',
        'location_id',
        'type',
        'mode',
        'montant',
        'reference_transaction',
        'date_paiement',
        'statut',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'montant' => 'decimal:2',
            'date_paiement' => 'datetime',
        ];
    }

    public function vente(): BelongsTo
    {
        return $this->belongsTo(Vente::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function recus(): HasMany
    {
        return $this->hasMany(Recu::class);
    }

    // Permet d'écrire Paiement::valide()->get()
    public function scopeValide(Builder $query): Builder
    {
        return $query->where('statut', 'valide');
    }
}