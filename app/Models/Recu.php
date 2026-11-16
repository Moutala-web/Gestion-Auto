<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Recu extends Model
{
    // Laravel devinerait « recus » : le nom est déjà correct, mais on le fixe pour être explicite
    protected $table = 'recus';

    // La table n'a pas de colonne updated_at
    public const UPDATED_AT = null;

    protected $fillable = [
        'numero',
        'vente_id',
        'location_id',
        'paiement_id',
        'date_generation',
        'chemin_fichier',
    ];

    protected function casts(): array
    {
        return [
            'date_generation' => 'datetime',
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

    public function paiement(): BelongsTo
    {
        return $this->belongsTo(Paiement::class);
    }
}