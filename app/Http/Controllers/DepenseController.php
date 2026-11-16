<?php

namespace App\Http\Controllers;

use App\Models\Depense;
use App\Models\MouvementCaisse;
use App\Models\VehiculeFrais;
use App\Services\CaisseService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DepenseController extends Controller
{
    // Catégories des dépenses de fonctionnement
    public const CATEGORIES = [
        'loyer' => 'Loyer',
        'electricite_eau' => 'Électricité et eau',
        'salaires' => 'Salaires',
        'carburant' => 'Carburant',
        'transport' => 'Transport',
        'communication' => 'Communication',
        'publicite' => 'Publicité',
        'entretien_local' => 'Entretien du local',
        'impots_taxes' => 'Impôts et taxes',
        'fournitures' => 'Fournitures',
        'autres' => 'Autres',
    ];

    /**
     * Journal de toutes les dépenses : achats (véhicules, motos, pièces), frais de véhicules
     * et dépenses de fonctionnement. Il est tiré du journal de caisse, donc toujours cohérent avec lui.
     */
    public function index(Request $request): View
    {
        $recherche = trim((string) $request->query('q', ''));
        $categorie = $request->query('categorie');
        $mode = $request->query('mode');

        // Par défaut : le mois en cours. Si on vide les dates, on voit tout l'historique.
        $du = $request->has('du') ? ($request->query('du') ?: null) : now()->startOfMonth()->toDateString();
        $au = $request->has('au') ? ($request->query('au') ?: null) : now()->toDateString();

        // Nature choisie dans les onglets : achat, frais_vehicule ou depense (vide = toutes)
        $natures = MouvementCaisse::NATURES_DEPENSES;
        $nature = array_key_exists((string) $request->query('nature'), $natures) ? $request->query('nature') : null;

        // Chaque nature est suivie de sa contrepartie d'annulation
        $categoriesCaisse = $nature
            ? [$nature, $natures[$nature]]
            : array_merge(array_keys($natures), array_values($natures));

        $mouvements = MouvementCaisse::query()
            ->with('user')
            ->whereIn('categorie', $categoriesCaisse)
            ->entre($du, $au)
            ->when($mode, fn ($q) => $q->where('mode_paiement', $mode))
            ->when($recherche !== '', fn ($q) => $q->where('description', 'like', "%{$recherche}%"))
            ->when($categorie, function ($q) use ($categorie) {
                $q->where('reference_type', 'depense')
                    ->whereIn('reference_id', Depense::where('categorie', $categorie)->pluck('id'));
            })
            ->orderByDesc('date_operation')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        // Chiffres de la période (indépendants du mode, de la recherche et de la catégorie)
        $totaux = MouvementCaisse::totauxDepenses($du, $au);

        // Détails des sources affichées sur cette page
        $page = $mouvements->getCollection();

        $depensesParId = Depense::whereIn('id', $page->where('reference_type', 'depense')->pluck('reference_id')->unique())
            ->get()
            ->keyBy('id');

        $fraisParId = VehiculeFrais::with('vehicule')
            ->whereIn('id', $page->where('reference_type', 'vehicule_frais')->pluck('reference_id')->unique())
            ->get()
            ->keyBy('id');

        $categories = self::CATEGORIES;

        return view('depenses.index', compact(
            'mouvements', 'totaux', 'nature', 'recherche', 'categorie', 'mode', 'du', 'au',
            'categories', 'depensesParId', 'fraisParId'
        ));
    }

    // Une dépense de fonctionnement sort immédiatement de la caisse
    public function store(Request $request, CaisseService $caisse): RedirectResponse
    {
        $data = $request->validate([
            'libelle' => ['required', 'string', 'max:255'],
            'categorie' => ['required', 'in:' . implode(',', array_keys(self::CATEGORIES))],
            'montant' => ['required', 'numeric', 'min:1'],
            'date_depense' => ['required', 'date'],
            'mode_paiement' => ['required', 'in:especes,orange_money'],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
        ], [
            'montant.min' => 'Le montant doit être supérieur à zéro.',
        ]);

        DB::transaction(function () use ($data, $request, $caisse) {
            $date = Carbon::parse($data['date_depense'])->setTimeFrom(now());

            $depense = Depense::create([
                'user_id' => $request->user()->id,
                'categorie' => $data['categorie'],
                'libelle' => $data['libelle'],
                'montant' => round((float) $data['montant'], 2),
                'date_depense' => $date,
                'mode_paiement' => $data['mode_paiement'],
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'statut' => 'valide',
            ]);

            $caisse->sortie(
                categorie: 'depense',
                montant: (float) $depense->montant,
                mode: $depense->mode_paiement,
                userId: $request->user()->id,
                refType: 'depense',
                refId: $depense->id,
                description: $depense->libelle,
                date: $date,
            );
        });

        return back()->with('succes', 'Dépense enregistrée.');
    }

    // Une dépense ne se supprime jamais : on l'annule, et la caisse enregistre la contrepartie
    public function annuler(Request $request, Depense $depense, CaisseService $caisse): RedirectResponse
    {
        $data = $request->validate([
            'motif' => ['required', 'string', 'max:255'],
        ], [
            'motif.required' => 'Indique le motif de l\'annulation.',
        ]);

        DB::transaction(function () use ($data, $depense, $request, $caisse) {
            $verrouillee = Depense::whereKey($depense->id)->lockForUpdate()->firstOrFail();

            if ($verrouillee->statut !== 'valide') {
                throw ValidationException::withMessages(['motif' => 'Cette dépense est déjà annulée.']);
            }

            $verrouillee->update([
                'statut' => 'annulee',
                'motif_annulation' => $data['motif'],
            ]);

            $caisse->entree(
                categorie: 'annulation_depense',
                montant: (float) $verrouillee->montant,
                mode: $verrouillee->mode_paiement,
                userId: $request->user()->id,
                refType: 'depense',
                refId: $verrouillee->id,
                description: 'Annulation : ' . $verrouillee->libelle,
            );
        });

        return back()->with('succes', 'Dépense annulée.');
    }
}