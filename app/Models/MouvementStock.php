<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MouvementStock extends Model
{
    // Laravel devinerait « mouvement_stocks » : on indique le vrai nom de la table
    protected $table = 'mouvements_stock';

    // La table n'a pas de colonne updated_at : un mouvement ne se modifie jamais
    public const UPDATED_AT = null;

    protected $fillable = [
        'piece_id',
        'type',
        'quantite',
        'prix_achat',
        'motif',
        'reference_operation',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'quantite' => 'integer',
            'prix_achat' => 'decimal:2',
        ];
    }

    public function piece(): BelongsTo
    {
        return $this->belongsTo(Piece::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}