<?php

namespace App\Http\Controllers;

use App\Models\Achat;
use App\Models\Modele;
use App\Models\Vehicule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class VehiculeController extends Controller
{
    // Statuts qu'on peut choisir à la main. « en_location » et « vendu » sont gérés
    // uniquement par le système (locations et ventes), jamais depuis le formulaire.
    private const STATUTS_MODIFIABLES = ['disponible', 'reserve', 'maintenance'];

    public function index(Request $request): View
    {
        $recherche = trim((string) $request->query('q', ''));
        $statut = $request->query('statut');

        $vehicules = Vehicule::query()
            ->withSum('frais', 'montant') // une seule requête pour tous les frais de la liste
            ->when($recherche !== '', function ($query) use ($recherche) {
                $query->where(function ($q) use ($recherche) {
                    $q->where('reference', 'like', "%{$recherche}%")
                        ->orWhere('marque', 'like', "%{$recherche}%")
                        ->orWhere('modele', 'like', "%{$recherche}%")
                        ->orWhere('immatriculation', 'like', "%{$recherche}%")
                        ->orWhere('numero_chassis', 'like', "%{$recherche}%");
                });
            })
            ->when($statut, fn ($query) => $query->where('statut', $statut))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $modeles = $this->modeles();

        return view('vehicules.index', compact('vehicules', 'recherche', 'statut', 'modeles'));
    }

    public function show(Vehicule $vehicule): View
    {
        $vehicule->load(['frais' => fn ($q) => $q->orderByDesc('date_frais')->orderByDesc('id')]);

        $modeles = $this->modeles();

        return view('vehicules.show', compact('vehicule', 'modeles'));
    }

    // Les fiches ne se créent pas à la main : elles naissent d'un achat. On ne modifie ici que leurs informations.
    public function update(Request $request, Vehicule $vehicule): RedirectResponse
    {
        $data = $this->valider($request, $vehicule);

        // Un véhicule loué ou vendu garde son statut : seul le système le change
        if (in_array($vehicule->statut, ['en_location', 'vendu'], true)) {
            unset($data['statut']);
        }

        $vehicule->update($data);

        return back()->with('succes', 'Informations du véhicule enregistrées.');
    }

    // On ne supprime jamais un véhicule qui a un historique (frais, achat, location ou vente)
    public function destroy(Vehicule $vehicule): RedirectResponse
    {
        if ($vehicule->frais()->exists()
            || $vehicule->locations()->exists()
            || $vehicule->venteDetails()->exists()
            || Achat::where('vehicule_id', $vehicule->id)->exists()) {
            return redirect()->route('vehicules.show', $vehicule)
                ->with('erreur', 'Ce véhicule a un historique (frais, achat, location ou vente) : suppression impossible.');
        }

        $vehicule->delete();

        return redirect()->route('vehicules.index')->with('succes', 'Véhicule supprimé.');
    }

    // Modèles de voitures du catalogue, proposés dans les formulaires
    private function modeles()
    {
        return Modele::where('type', 'voiture')->orderBy('marque')->orderBy('modele')->get();
    }

    private function valider(Request $request, Vehicule $vehicule): array
    {
        $data = $request->validate([
            'modele_id' => ['required', Rule::exists('modeles', 'id')->where('type', 'voiture')],
            'annee' => ['nullable', 'integer', 'between:1950,' . ((int) date('Y') + 1)],
            'immatriculation' => ['nullable', 'string', 'max:30', Rule::unique('vehicules', 'immatriculation')->ignore($vehicule)],
            'numero_chassis' => ['nullable', 'string', 'max:50', Rule::unique('vehicules', 'numero_chassis')->ignore($vehicule)],
            'couleur' => ['nullable', 'string', 'max:50'],
            'kilometrage' => ['nullable', 'integer', 'min:0'],
            'description' => ['nullable', 'string'],
            'prix_vente' => ['nullable', 'numeric', 'min:0'],
            'statut' => ['sometimes', Rule::in(self::STATUTS_MODIFIABLES)],
        ], [
            'modele_id.required' => 'Choisis un modèle dans le catalogue.',
            'modele_id.exists' => 'Ce modèle n\'existe pas dans le catalogue des voitures.',
            'numero_chassis.unique' => 'Ce numéro de châssis est déjà enregistré.',
        ]);

        // La fiche reprend la marque et le modèle du catalogue
        $modele = Modele::findOrFail($data['modele_id']);
        $data['marque'] = $modele->marque;
        $data['modele'] = $modele->modele;

        $data['numero_chassis'] = filled($data['numero_chassis'] ?? null) ? strtoupper(trim($data['numero_chassis'])) : null;
        $data['kilometrage'] = $data['kilometrage'] ?? 0;

        return $data;
    }
}