<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VenteDetail extends Model
{
    protected $table = 'vente_details';

    // La table n'a pas de colonne updated_at
    public const UPDATED_AT = null;

    protected $fillable = [
        'vente_id',
        'type_article',
        'vehicule_id',
        'moto_id',
        'piece_id',
        'designation',
        'quantite',
        'prix_unitaire',
        'total',
    ];

    protected function casts(): array
    {
        return [
            'quantite' => 'integer',
            'prix_unitaire' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function vente(): BelongsTo
    {
        return $this->belongsTo(Vente::class);
    }

    public function vehicule(): BelongsTo
    {
        return $this->belongsTo(Vehicule::class);
    }

    public function moto(): BelongsTo
    {
        return $this->belongsTo(Moto::class);
    }

    public function piece(): BelongsTo
    {
        return $this->belongsTo(Piece::class);
    }

    // Renvoie l'article réellement vendu (véhicule, moto ou pièce) selon type_article
    public function getArticleAttribute(): Vehicule|Moto|Piece|null
    {
        return match ($this->type_article) {
            'vehicule' => $this->vehicule,
            'moto' => $this->moto,
            'piece' => $this->piece,
            default => null,
        };
    }
}