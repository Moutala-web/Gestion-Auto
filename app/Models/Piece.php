<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Piece extends Model
{
    // Une « pièce » regroupe deux familles gérées de la même façon (stock, achats, ventes)
    public const FAMILLES = [
        'piece' => 'Pièce détachée',
        'divers' => 'Article divers',
    ];

    protected $fillable = [
        'reference',
        'modele_id',
        'famille',
        'designation',
        'categorie',
        'marque',
        'prix_achat',
        'prix_vente',
        'quantite_stock',
        'seuil_alerte',
        'emplacement',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'prix_achat' => 'decimal:2',
            'prix_vente' => 'decimal:2',
            'quantite_stock' => 'integer',
            'seuil_alerte' => 'integer',
        ];
    }

    // La définition de cette pièce dans le catalogue
    public function catalogue(): BelongsTo
    {
        return $this->belongsTo(Modele::class, 'modele_id');
    }

    public function mouvements(): HasMany
    {
        return $this->hasMany(MouvementStock::class);
    }

    public function venteDetails(): HasMany
    {
        return $this->hasMany(VenteDetail::class);
    }

    public function getLibelleFamilleAttribute(): string
    {
        return self::FAMILLES[$this->famille] ?? (string) $this->famille;
    }

    public function estDivers(): bool
    {
        return $this->famille === 'divers';
    }

    /**
     * Réception d'une quantité achetée : le stock augmente et le prix d'achat devient
     * le prix moyen pondéré (stock en place × ancien prix + quantité reçue × nouveau prix).
     * À appeler dans une transaction, sur une ligne verrouillée.
     */
    public function appliquerReception(int $quantite, float $prix): void
    {
        $stock = max((int) $this->quantite_stock, 0);
        $total = $stock + $quantite;

        $this->prix_achat = $total > 0
            ? round((($stock * (float) $this->prix_achat) + ($quantite * $prix)) / $total, 2)
            : $prix;

        $this->quantite_stock = $total;
        $this->save();
    }

    // Marge unitaire prévisionnelle
    public function getMargeUnitaireAttribute(): float
    {
        return (float) $this->prix_vente - (float) $this->prix_achat;
    }

    // Vrai quand le stock est au niveau d'alerte ou en dessous
    public function estEnAlerte(): bool
    {
        return $this->quantite_stock <= $this->seuil_alerte;
    }

    // Permet d'écrire Piece::enAlerte()->get()
    public function scopeEnAlerte(Builder $query): Builder
    {
        return $query->whereColumn('quantite_stock', '<=', 'seuil_alerte');
    }

    // Permet d'écrire Piece::deFamille('divers')->get()
    public function scopeDeFamille(Builder $query, string $famille): Builder
    {
        return $query->where('famille', $famille);
    }
}