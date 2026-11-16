<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Achat extends Model
{
    protected $fillable = [
        'type_article',
        'vehicule_id',
        'moto_id',
        'piece_id',
        'fournisseur',
        'quantite',
        'prix_unitaire',
        'montant',
        'mode_paiement',
        'reference_transaction',
        'date_achat',
        'user_id',
        'notes',
        'statut',
        'motif_annulation',
    ];

    protected function casts(): array
    {
        return [
            'quantite' => 'integer',
            'prix_unitaire' => 'decimal:2',
            'montant' => 'decimal:2',
            'date_achat' => 'datetime',
        ];
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Numéro lisible (A-00012), calculé à partir de l'identifiant
    public function getNumeroAttribute(): string
    {
        return sprintf('A-%05d', $this->id);
    }

    // Renvoie l'article réellement acheté (véhicule, moto ou pièce) selon type_article
    public function getArticleAttribute(): Vehicule|Moto|Piece|null
    {
        return match ($this->type_article) {
            'vehicule' => $this->vehicule,
            'moto' => $this->moto,
            'piece' => $this->piece,
            default => null,
        };
    }

    // Nom de l'article pour l'affichage
    public function getLibelleArticleAttribute(): string
    {
        $article = $this->article;

        if (! $article) {
            return 'Article supprimé';
        }

        return $this->type_article === 'piece'
            ? "{$article->designation} ({$article->reference})"
            : "{$article->marque} {$article->modele} ({$article->reference})";
    }

    // Les achats annulés ne comptent jamais dans les totaux
    public function scopeValides(Builder $query): Builder
    {
        return $query->where('statut', 'valide');
    }
}