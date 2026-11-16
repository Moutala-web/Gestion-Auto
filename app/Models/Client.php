<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    protected $fillable = [
        'nom',
        'prenom',
        'telephone',
        'email',
        'adresse',
        'type',
        'notes',
    ];

    public function ventes(): HasMany
    {
        return $this->hasMany(Vente::class);
    }

    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }

    // Permet d'écrire $client->nom_complet
    public function getNomCompletAttribute(): string
    {
        return trim($this->prenom . ' ' . $this->nom);
    }
}