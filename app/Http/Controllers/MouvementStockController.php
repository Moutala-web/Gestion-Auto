<?php

namespace App\Http\Controllers;

use App\Models\Piece;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MouvementStockController extends Controller
{
    /**
     * Mouvements manuels : seulement des SORTIES de stock. Une entrée de stock passe toujours par un achat.
     *  - sortie : usage interne, don, échantillon (quantité positive)
     *  - ajustement : casse, perte, écart d'inventaire (quantité négative uniquement)
     */
    public function store(Request $request, Piece $piece): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['sortie', 'ajustement'])],
            'quantite' => ['required', 'integer', 'not_in:0'],
            'motif' => ['nullable', 'string', 'max:255'],
            'reference_operation' => ['nullable', 'string', 'max:100'],
        ]);

        if ($data['type'] === 'sortie' && $data['quantite'] < 0) {
            throw ValidationException::withMessages([
                'quantite' => 'La quantité d\'une sortie doit être positive.',
            ]);
        }

        if ($data['type'] === 'ajustement' && $data['quantite'] > 0) {
            throw ValidationException::withMessages([
                'quantite' => 'Un ajustement ne peut que retirer du stock : saisis un nombre négatif. Pour ajouter du stock, enregistre un achat.',
            ]);
        }

        if ($data['type'] === 'ajustement' && blank($data['motif'] ?? null)) {
            throw ValidationException::withMessages([
                'motif' => 'Le motif est obligatoire pour un ajustement.',
            ]);
        }

        // Transaction + verrou : deux opérations simultanées ne peuvent pas rendre le stock faux
        DB::transaction(function () use ($data, $piece) {
            $verrouillee = Piece::whereKey($piece->id)->lockForUpdate()->firstOrFail();

            $variation = $data['type'] === 'sortie' ? -$data['quantite'] : $data['quantite'];
            $nouveauStock = $verrouillee->quantite_stock + $variation;

            if ($nouveauStock < 0) {
                throw ValidationException::withMessages([
                    'quantite' => "Stock insuffisant : il reste {$verrouillee->quantite_stock} en stock.",
                ]);
            }

            $verrouillee->quantite_stock = $nouveauStock;
            $verrouillee->save();

            $verrouillee->mouvements()->create([
                'type' => $data['type'],
                'quantite' => $data['quantite'],
                'motif' => $data['motif'] ?? null,
                'reference_operation' => $data['reference_operation'] ?? null,
                'user_id' => auth()->id(),
            ]);
        });

        return back()->with('succes', 'Mouvement de stock enregistré.');
    }
}