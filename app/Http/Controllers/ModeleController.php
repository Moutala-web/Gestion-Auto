<?php

namespace App\Http\Controllers;

use App\Models\Modele;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ModeleController extends Controller
{
    // Catégories proposées à la saisie, en plus de celles déjà utilisées
    private const CATEGORIES = [
        'Berline', 'SUV', 'Pick-up', 'Citadine', 'Utilitaire', 'Monospace',
        'Routière', 'Custom', 'Sportive', 'Trail', 'Scooter',
        'Entretien', 'Freinage', 'Électricité', 'Moteur', 'Carrosserie',
        'Chaises', 'Matelas', 'Vélos',
    ];

    public function index(Request $request): View
    {
        $recherche = trim((string) $request->query('q', ''));
        $marque = $request->query('marque');
        $type = in_array($request->query('type'), array_keys(Modele::TYPES), true) ? $request->query('type') : null;

        $modeles = Modele::query()
            ->when($type, fn ($q) => $q->where('type', $type))
            ->when($marque, fn ($q) => $q->where('marque', $marque))
            ->when($recherche !== '', function ($query) use ($recherche) {
                $query->where(function ($q) use ($recherche) {
                    $q->where('marque', 'like', "%{$recherche}%")
                        ->orWhere('modele', 'like', "%{$recherche}%")
                        ->orWhere('categorie', 'like', "%{$recherche}%");
                });
            })
            ->withCount([
                'vehicules',
                'vehicules as vehicules_disponibles_count' => fn ($q) => $q->where('statut', 'disponible'),
                'vehicules as vehicules_vendus_count' => fn ($q) => $q->where('statut', 'vendu'),
                'motos',
                'motos as motos_disponibles_count' => fn ($q) => $q->where('statut', 'disponible'),
                'motos as motos_vendues_count' => fn ($q) => $q->where('statut', 'vendu'),
                'pieces',
            ])
            ->withSum('pieces', 'quantite_stock')
            ->orderBy('marque')
            ->orderBy('modele')
            ->paginate(20)
            ->withQueryString();

        $parType = Modele::selectRaw('type, COUNT(*) as total')->groupBy('type')->pluck('total', 'type');

        $compteurs = ['' => (int) $parType->sum()];
        foreach (array_keys(Modele::TYPES) as $t) {
            $compteurs[$t] = (int) ($parType[$t] ?? 0);
        }

        $marques = Modele::query()
            ->when($type, fn ($q) => $q->where('type', $type))
            ->where('marque', '!=', '')
            ->distinct()->orderBy('marque')->pluck('marque');

        $suggestions = Modele::query()
            ->whereNotNull('categorie')->where('categorie', '!=', '')
            ->distinct()->pluck('categorie')
            ->merge(self::CATEGORIES)
            ->unique()->sort()->values()->all();

        return view('modeles.index', compact('modeles', 'recherche', 'marque', 'type', 'compteurs', 'marques', 'suggestions'));
    }

    // La création et la modification se font dans des fenêtres (modales)
    public function store(Request $request): RedirectResponse
    {
        Modele::create($this->valider($request));

        return back()->with('succes', 'Ajouté au catalogue.');
    }

    public function update(Request $request, Modele $modele): RedirectResponse
    {
        $data = $this->valider($request, $modele);

        // Le type ne peut plus changer dès que des fiches ou du stock utilisent ce modèle
        if ($data['type'] !== $modele->type && $this->estUtilise($modele)) {
            throw ValidationException::withMessages([
                'type' => 'Le type ne peut plus changer : des fiches ou du stock utilisent déjà ce modèle.',
            ]);
        }

        DB::transaction(function () use ($modele, $data) {
            $modele->update($data);

            // Les fiches reprennent la marque et le modèle du catalogue
            $modele->vehicules()->update(['marque' => $modele->marque, 'modele' => $modele->modele]);
            $modele->motos()->update(['marque' => $modele->marque, 'modele' => $modele->modele]);

            // Une pièce reprend sa désignation, sa marque et sa catégorie
            $modele->pieces()->update([
                'designation' => $modele->modele,
                'marque' => filled($modele->marque) ? $modele->marque : null,
                'categorie' => $modele->categorie,
                'famille' => $modele->type,
            ]);
        });

        return back()->with('succes', 'Modifié : les fiches liées ont été mises à jour.');
    }

    // On ne supprime jamais un modèle utilisé par des fiches ou du stock
    public function destroy(Modele $modele): RedirectResponse
    {
        if ($this->estUtilise($modele)) {
            return redirect()->route('modeles.index')
                ->with('erreur', 'Ce modèle est utilisé par des véhicules, des motos ou du stock : suppression impossible.');
        }

        $modele->delete();

        return redirect()->route('modeles.index')->with('succes', 'Supprimé du catalogue.');
    }

    private function estUtilise(Modele $modele): bool
    {
        return $modele->vehicules()->exists() || $modele->motos()->exists() || $modele->pieces()->exists();
    }

    private function valider(Request $request, ?Modele $modele = null): array
    {
        $type = $request->input('type');
        $fiche = in_array($type, ['voiture', 'moto'], true);
        $stock = in_array($type, ['piece', 'divers'], true);

        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(Modele::TYPES))],
            'marque' => [Rule::requiredIf($fiche), 'nullable', 'string', 'max:100'],
            'modele' => [
                'required', 'string', 'max:100',
                Rule::unique('modeles', 'modele')
                    ->where(fn ($q) => $q->where('type', $type)->where('marque', (string) $request->input('marque', '')))
                    ->ignore($modele),
            ],
            // La catégorie est obligatoire pour une pièce ou un article divers
            'categorie' => [Rule::requiredIf($stock), 'nullable', 'string', 'max:100'],
            'prix_vente_indicatif' => [Rule::requiredIf($stock), 'nullable', 'numeric', 'min:0'],
            'seuil_alerte' => ['nullable', 'integer', 'min:0'],
            'cylindree' => ['nullable', 'integer', 'min:1', 'max:9999'],
            'carburant' => ['nullable', Rule::in(array_keys(Modele::CARBURANTS))],
            'boite' => ['nullable', Rule::in(array_keys(Modele::BOITES))],
        ], [
            'marque.required' => 'La marque est obligatoire pour une voiture ou une moto.',
            'modele.required' => 'Le modèle (ou la désignation) est obligatoire.',
            'modele.unique' => 'Cet élément existe déjà dans le catalogue.',
            'categorie.required' => 'Choisis une catégorie (par exemple Chaises, Matelas ou Pneus et jantes).',
            'prix_vente_indicatif.required' => 'Le prix de vente est obligatoire pour une pièce ou un article divers.',
        ]);

        $data['marque'] = trim((string) ($data['marque'] ?? ''));
        $data['modele'] = trim($data['modele']);

        // Pièces et articles divers : catégorie nettoyée (espaces retirés, première lettre en majuscule)
        if ($stock) {
            $categorie = trim((string) ($data['categorie'] ?? ''));
            $data['categorie'] = $categorie === ''
                ? null
                : mb_strtoupper(mb_substr($categorie, 0, 1)).mb_substr($categorie, 1);
        }

        // Chaque caractéristique ne concerne qu'un type
        $data['seuil_alerte'] = $stock ? ($data['seuil_alerte'] ?? 0) : 0;

        if ($type !== 'moto') {
            $data['cylindree'] = null;
        }

        if ($type !== 'voiture') {
            $data['carburant'] = null;
            $data['boite'] = null;
        }

        return $data;
    }
}