<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Location extends Model
{
    protected $fillable = [
        'numero',
        'client_id',
        'vehicule_id',
        'user_id',
        'date_depart',
        'date_retour_prevue',
        'date_retour_reelle',
        'tarif',
        'caution',
        'kilometrage_depart',
        'kilometrage_retour',
        'etat_depart',
        'etat_retour',
        'retenue_caution',
        'caution_remboursee',
        'statut',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'date_depart' => 'datetime',
            'date_retour_prevue' => 'datetime',
            'date_retour_reelle' => 'datetime',
            'tarif' => 'decimal:2',
            'caution' => 'decimal:2',
            'retenue_caution' => 'decimal:2',
            'caution_remboursee' => 'decimal:2',
            'kilometrage_depart' => 'integer',
            'kilometrage_retour' => 'integer',
        ];
    }

    // ---------- Relations ----------

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function vehicule(): BelongsTo
    {
        return $this->belongsTo(Vehicule::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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

    // Total des paiements de location valides (hors caution)
    public function getTotalPayeAttribute(): float
    {
        return (float) $this->paiements()
            ->where('type', 'location')
            ->where('statut', 'valide')
            ->sum('montant');
    }

    public function getResteAPayerAttribute(): float
    {
        return (float) $this->tarif - $this->total_paye;
    }

    // Caution réellement encaissée
    public function getCautionVerseeAttribute(): float
    {
        return (float) $this->paiements()
            ->where('type', 'caution')
            ->where('statut', 'valide')
            ->sum('montant');
    }

    // Ce qu'il reste à rendre au client : caution - retenue - déjà remboursé
    public function getCautionARembourserAttribute(): float
    {
        return (float) $this->caution - (float) $this->retenue_caution - (float) $this->caution_remboursee;
    }

    // ---------- Scopes ----------

    // Locations actives (réservées ou en cours) du même véhicule qui chevauchent la période demandée.
    // Chevauchement : début existant < fin demandée ET fin existante > début demandé.
    // Usage : Location::chevauche($vehiculeId, $debut, $fin)->exists()
    public function scopeChevauche(Builder $query, int $vehiculeId, $debut, $fin): Builder
    {
        return $query
            ->where('vehicule_id', $vehiculeId)
            ->whereIn('statut', ['reservee', 'en_cours'])
            ->where('date_depart', '<', $fin)
            ->where('date_retour_prevue', '>', $debut);
    }
}