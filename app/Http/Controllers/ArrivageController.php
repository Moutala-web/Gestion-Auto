<?php

namespace App\Http\Controllers;

use App\Services\ArrivageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ArrivageController extends Controller
{
    public function store(Request $request, ArrivageService $service): RedirectResponse
    {
        $data = $request->validate([
            'fournisseur' => ['nullable', 'string', 'max:150'],
            'date_achat' => ['required', 'date'],
            'enregistrer_en_caisse' => ['nullable', 'boolean'],
            'mode_paiement' => ['required', 'in:especes,orange_money'],
            'reference_transaction' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
            'lignes' => ['required', 'array', 'min:1', 'max:30'],
            'lignes.*.type' => ['required', 'in:vehicule,moto,piece'],
            'lignes.*.modele_id' => ['required', 'integer'],
            'lignes.*.quantite' => ['required', 'integer', 'min:1', 'max:1000'],
            'lignes.*.prix_unitaire' => ['required', 'numeric', 'min:1'],
            'lignes.*.annee' => ['nullable', 'integer', 'between:1950,' . ((int) date('Y') + 1)],
            'lignes.*.couleur' => ['nullable', 'string', 'max:50'],
        ], [
            'date_achat.required' => 'Indique la date de l\'achat.',
            'lignes.required' => 'Ajoute au moins une ligne à l\'achat.',
            'lignes.max' => 'Un achat ne peut pas dépasser 30 lignes.',
            'lignes.*.modele_id.required' => 'Choisis un élément du catalogue pour chaque ligne.',
            'lignes.*.quantite.required' => 'Indique la quantité de chaque ligne.',
            'lignes.*.quantite.integer' => 'La quantité doit être un nombre entier.',
            'lignes.*.quantite.min' => 'La quantité doit être d\'au moins 1.',
            'lignes.*.quantite.max' => 'La quantité d\'une ligne ne peut pas dépasser 1 000.',
            'lignes.*.prix_unitaire.required' => 'Indique le prix unitaire de chaque ligne.',
            'lignes.*.prix_unitaire.numeric' => 'Le prix unitaire doit être un nombre.',
            'lignes.*.prix_unitaire.min' => 'Le prix unitaire doit être supérieur à zéro.',
            'lignes.*.annee.between' => 'L\'année d\'une ligne est invalide.',
        ]);

        $resume = $service->creer($data, $request->user()->id);

        $parties = array_filter([
            $resume['vehicules'] ? "{$resume['vehicules']} véhicule(s)" : null,
            $resume['motos'] ? "{$resume['motos']} moto(s)" : null,
            $resume['pieces'] ? "{$resume['pieces']} pièce(s) ou article(s)" : null,
        ]);

        $message = 'Achat enregistré : ' . implode(', ', $parties)
            . ' pour ' . number_format($resume['montant'], 0, ',', ' ') . ' FCFA'
            . ($resume['en_caisse'] ? ' (sorti de la caisse).' : ' (sans écriture en caisse).');

        if ($resume['references']) {
            $affichees = array_slice($resume['references'], 0, 12);

            $message .= ' Fiches créées : ' . implode(', ', $affichees)
                . (count($resume['references']) > 12 ? '...' : '')
                . ' — complète maintenant leur immatriculation, leur couleur et leur numéro de châssis.';
        }

        return back()->with('succes', $message);
    }
}