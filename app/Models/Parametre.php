<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Parametre extends Model
{
    protected $fillable = [
        'nom_entreprise',
        'telephone',
        'email',
        'adresse',
        'logo',
        'devise',
    ];

    // Il n'y a qu'une seule ligne de paramètres : on la récupère avec Parametre::actuel()
    public static function actuel(): self
    {
        return static::first() ?? new static([
            'nom_entreprise' => 'Mon entreprise',
            'devise' => 'FCFA',
        ]);
    }
}