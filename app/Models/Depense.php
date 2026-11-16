<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Depense extends Model
{
    protected $fillable = [
        'user_id',
        'categorie',
        'libelle',
        'montant',
        'date_depense',
        'mode_paiement',
        'reference',
        'notes',
        'statut',
        'motif_annulation',
    ];

    protected function casts(): array
    {
        return [
            'montant' => 'decimal:2',
            'date_depense' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Les dépenses annulées ne comptent jamais dans les totaux
    public function scopeValides(Builder $query): Builder
    {
        return $query->where('statut', 'valide');
    }
}