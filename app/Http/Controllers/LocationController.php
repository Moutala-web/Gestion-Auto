<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Location;
use App\Models\Vehicule;
use App\Services\LocationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LocationController extends Controller
{
    public function index(Request $request): View
    {
        $recherche = trim((string) $request->query('q', ''));
        $statut = $request->query('statut');

        $locations = Location::query()
            ->with(['client', 'vehicule'])
            ->when($recherche !== '', function ($query) use ($recherche) {
                $query->where(function ($q) use ($recherche) {
                    $q->where('numero', 'like', "%{$recherche}%")
                        ->orWhereHas('client', function ($c) use ($recherche) {
                            $c->where('nom', 'like', "%{$recherche}%")
                                ->orWhere('prenom', 'like', "%{$recherche}%")
                                ->orWhere('telephone', 'like', "%{$recherche}%");
                        })
                        ->orWhereHas('vehicule', function ($v) use ($recherche) {
                            $v->where('reference', 'like', "%{$recherche}%")
                                ->orWhere('marque', 'like', "%{$recherche}%")
                                ->orWhere('modele', 'like', "%{$recherche}%");
                        });
                });
            })
            ->when($statut, fn ($query) => $query->where('statut', $statut))
            ->orderByDesc('date_depart')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        // Véhicules proposés dans la fenêtre « Nouvelle location » : tous sauf vendus ou en maintenance
        $vehicules = Vehicule::whereNotIn('statut', ['vendu', 'maintenance'])
            ->orderBy('marque')->orderBy('modele')->get()
            ->map(fn ($v) => [
                'id' => $v->id,
                'label' => "{$v->marque} {$v->modele} ({$v->reference})",
                'km' => (int) $v->kilometrage,
            ])->values()->all();

        $clients = Client::orderBy('nom')->orderBy('prenom')->get();

        return view('locations.index', compact('locations', 'recherche', 'statut', 'vehicules', 'clients'));
    }

    public function store(Request $request, LocationService $service): RedirectResponse
    {
        $data = $request->validate([
            'client_id' => ['required', 'exists:clients,id'],
            'vehicule_id' => ['required', 'exists:vehicules,id'],
            'date_depart' => ['required', 'date'],
            'date_retour_prevue' => ['required', 'date', 'after:date_depart'],
            'tarif' => ['required', 'numeric', 'min:1'],
            'caution' => ['nullable', 'numeric', 'min:0'],
            'kilometrage_depart' => ['nullable', 'integer', 'min:0'],
            'etat_depart' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ], [
            'client_id.required' => 'Choisis un client.',
            'vehicule_id.required' => 'Choisis un véhicule.',
            'date_retour_prevue.after' => 'Le retour doit être après le départ.',
            'tarif.min' => 'Le tarif doit être supérieur à zéro.',
        ]);

        $location = $service->creer($data, $request->user()->id);

        return redirect()->route('locations.show', $location)
            ->with('succes', "Location {$location->numero} enregistrée.");
    }

    public function show(Location $location): View
    {
        $location->load(['client', 'vehicule', 'user', 'paiements.recus']);

        return view('locations.show', compact('location'));
    }

    public function demarrer(Request $request, Location $location, LocationService $service): RedirectResponse
    {
        $data = $request->validate([
            'kilometrage_depart' => ['required', 'integer', 'min:0'],
            'etat_depart' => ['nullable', 'string'],
        ]);

        $service->demarrer($location, $data);

        return redirect()->route('locations.show', $location)->with('succes', 'Location démarrée.');
    }

    public function paiement(Request $request, Location $location, LocationService $service): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:location,caution'],
            'mode' => ['required', 'in:especes,orange_money'],
            'montant' => ['required', 'numeric', 'min:1'],
            'reference_transaction' => ['nullable', 'required_if:mode,orange_money', 'string', 'max:100'],
        ], [
            'montant.min' => 'Le montant doit être supérieur à zéro.',
            'reference_transaction.required_if' => 'La référence de la transaction Orange Money est obligatoire.',
        ]);

        $service->encaisser($location, $data, $request->user()->id);

        return redirect()->route('locations.show', $location)->with('succes', 'Paiement enregistré.');
    }

    public function terminer(Request $request, Location $location, LocationService $service): RedirectResponse
    {
        $data = $request->validate([
            'kilometrage_retour' => ['required', 'integer', 'min:0'],
            'etat_retour' => ['nullable', 'string'],
            'retenue_caution' => ['nullable', 'numeric', 'min:0'],
            'mode_remboursement' => ['required', 'in:especes,orange_money'],
        ]);

        $service->terminer($location, $data, $request->user()->id);

        return redirect()->route('locations.show', $location)->with('succes', 'Retour enregistré, location terminée.');
    }

    public function annuler(Request $request, Location $location, LocationService $service): RedirectResponse
    {
        $data = $request->validate([
            'motif' => ['required', 'string', 'max:255'],
        ], [
            'motif.required' => 'Indique le motif de l\'annulation.',
        ]);

        $service->annuler($location, $data['motif'], $request->user()->id);

        return redirect()->route('locations.show', $location)->with('succes', 'Location annulée.');
    }
}