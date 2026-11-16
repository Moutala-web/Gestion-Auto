<?php

namespace App\Http\Controllers;

use App\Models\Achat;
use App\Models\Modele;
use App\Models\Moto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MotoController extends Controller
{
    // « vendu » est géré uniquement par les ventes, jamais depuis le formulaire
    private const STATUTS_MODIFIABLES = ['disponible', 'reserve'];

    public function index(Request $request): View
    {
        $recherche = trim((string) $request->query('q', ''));
        $statut = $request->query('statut');

        $motos = Moto::query()
            ->when($recherche !== '', function ($query) use ($recherche) {
                $query->where(function ($q) use ($recherche) {
                    $q->where('reference', 'like', "%{$recherche}%")
                        ->orWhere('marque', 'like', "%{$recherche}%")
                        ->orWhere('modele', 'like', "%{$recherche}%")
                        ->orWhere('numero_chassis', 'like', "%{$recherche}%");
                });
            })
            ->when($statut, fn ($query) => $query->where('statut', $statut))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        // Modèles de motos du catalogue, proposés dans les formulaires
        $modeles = Modele::where('type', 'moto')->orderBy('marque')->orderBy('modele')->get();

        return view('motos.index', compact('motos', 'recherche', 'statut', 'modeles'));
    }

    // Les fiches ne se créent pas à la main : elles naissent d'un achat. On ne modifie ici que leurs informations.
    public function update(Request $request, Moto $moto): RedirectResponse
    {
        $data = $this->valider($request, $moto);

        // Une moto vendue garde son statut
        if ($moto->statut === 'vendu') {
            unset($data['statut']);
        }

        $moto->update($data);

        return back()->with('succes', 'Informations de la moto enregistrées.');
    }

    // On ne supprime jamais une moto qui a un historique
    public function destroy(Moto $moto): RedirectResponse
    {
        if ($moto->venteDetails()->exists() || Achat::where('moto_id', $moto->id)->exists()) {
            return redirect()->route('motos.index')
                ->with('erreur', 'Cette moto a un historique de vente ou d\'achat : suppression impossible.');
        }

        $moto->delete();

        return redirect()->route('motos.index')->with('succes', 'Moto supprimée.');
    }

    private function valider(Request $request, Moto $moto): array
    {
        $data = $request->validate([
            'modele_id' => ['required', Rule::exists('modeles', 'id')->where('type', 'moto')],
            'annee' => ['nullable', 'integer', 'between:1950,' . ((int) date('Y') + 1)],
            'couleur' => ['nullable', 'string', 'max:50'],
            'numero_chassis' => ['nullable', 'string', 'max:50', Rule::unique('motos', 'numero_chassis')->ignore($moto)],
            'kilometrage' => ['nullable', 'integer', 'min:0'],
            'prix_vente' => ['nullable', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'statut' => ['sometimes', Rule::in(self::STATUTS_MODIFIABLES)],
        ], [
            'modele_id.required' => 'Choisis un modèle dans le catalogue.',
            'modele_id.exists' => 'Ce modèle n\'existe pas dans le catalogue des motos.',
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