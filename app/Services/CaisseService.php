<?php

namespace App\Services;

use App\Models\MouvementCaisse;
use Carbon\CarbonInterface;

class CaisseService
{
    // Argent qui entre en caisse
    public function entree(
        string $categorie,
        float $montant,
        string $mode,
        int $userId,
        ?string $refType = null,
        ?int $refId = null,
        ?string $description = null,
        ?CarbonInterface $date = null,
    ): MouvementCaisse {
        return $this->enregistrer('entree', $categorie, $montant, $mode, $userId, $refType, $refId, $description, $date);
    }

    // Argent qui sort de la caisse
    public function sortie(
        string $categorie,
        float $montant,
        string $mode,
        int $userId,
        ?string $refType = null,
        ?int $refId = null,
        ?string $description = null,
        ?CarbonInterface $date = null,
    ): MouvementCaisse {
        return $this->enregistrer('sortie', $categorie, $montant, $mode, $userId, $refType, $refId, $description, $date);
    }

    private function enregistrer(
        string $type,
        string $categorie,
        float $montant,
        string $mode,
        int $userId,
        ?string $refType,
        ?int $refId,
        ?string $description,
        ?CarbonInterface $date,
    ): MouvementCaisse {
        return MouvementCaisse::create([
            'user_id' => $userId,
            'type' => $type,
            'categorie' => $categorie,
            'montant' => round($montant, 2),
            'mode_paiement' => $mode,
            'reference_type' => $refType,
            'reference_id' => $refId,
            'date_operation' => $date ?? now(),
            'description' => $description,
        ]);
    }
}