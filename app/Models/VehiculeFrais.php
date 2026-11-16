<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehiculeFrais extends Model
{
    // Laravel ne devine pas ce nom de table correctement, on l'indique
    protected $table = 'vehicule_frais';

    protected $fillable = [
        'vehicule_id',
        'libelle',
        'categorie',
        'montant',
        'mode_paiement',
        'date_frais',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'montant' => 'decimal:2',
            'date_frais' => 'date',
        ];
    }

    public function vehicule(): BelongsTo
    {
        return $this->belongsTo(Vehicule::class);
    }
}