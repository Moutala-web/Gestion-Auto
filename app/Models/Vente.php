<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vente extends Model
{
    protected $fillable = [
        'numero',
        'client_id',
        'user_id',
        'date_vente',
        'sous_total',
        'remise',
        'total',
        'statut',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'date_vente' => 'datetime',
            'sous_total' => 'decimal:2',
            'remise' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    // ---------- Relations ----------

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function details(): HasMany
    {
        return $this->hasMany(VenteDetail::class);
    }

    public function paiements(): HasMany
    {
        return $this->hasMany(Paiement::class);
    }

    public function recus(): HasMany
    {
        return $this->hasMany(Recu::class);
    }

    // ---------- Calculs ----------

    // Somme des paiements valides (les paiements annulés ne comptent pas)
    public function getTotalPayeAttribute(): float
    {
        return (float) $this->paiements()->where('statut', 'valide')->sum('montant');
    }

    public function getResteAPayerAttribute(): float
    {
        return (float) $this->total - $this->total_paye;
    }

    public function estValidee(): bool
    {
        return $this->statut === 'validee';
    }
}