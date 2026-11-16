<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClientController extends Controller
{
    // Liste avec recherche par nom, prénom ou téléphone
    public function index(Request $request): View
    {
        $recherche = trim((string) $request->query('q', ''));

        $clients = Client::query()
            ->when($recherche !== '', function ($query) use ($recherche) {
                $query->where(function ($q) use ($recherche) {
                    $q->where('nom', 'like', "%{$recherche}%")
                        ->orWhere('prenom', 'like', "%{$recherche}%")
                        ->orWhere('telephone', 'like', "%{$recherche}%");
                });
            })
            ->orderBy('nom')
            ->paginate(15)
            ->withQueryString();

        return view('clients.index', compact('clients', 'recherche'));
    }

    // Les formulaires de création et de modification sont des fenêtres (modales)
    // dans les pages liste et fiche : on revient donc à la page d'origine.
    public function store(Request $request): RedirectResponse
    {
        Client::create($this->valider($request));

        return back()->with('succes', 'Client enregistré.');
    }

    // Fiche d'un client avec son historique de ventes et de locations
    public function show(Client $client): View
    {
        $client->load([
            'ventes' => fn ($q) => $q->latest('date_vente'),
            'locations' => fn ($q) => $q->latest('date_depart'),
        ]);

        return view('clients.show', compact('client'));
    }

    public function update(Request $request, Client $client): RedirectResponse
    {
        $client->update($this->valider($request));

        return back()->with('succes', 'Client modifié.');
    }

    // On ne supprime jamais un client qui a un historique
    public function destroy(Client $client): RedirectResponse
    {
        if ($client->ventes()->exists() || $client->locations()->exists()) {
            return redirect()->route('clients.index')
                ->with('erreur', 'Ce client a des ventes ou des locations : suppression impossible.');
        }

        $client->delete();

        return redirect()->route('clients.index')->with('succes', 'Client supprimé.');
    }

    private function valider(Request $request): array
    {
        return $request->validate([
            'nom' => ['required', 'string', 'max:100'],
            'prenom' => ['nullable', 'string', 'max:100'],
            'telephone' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'adresse' => ['nullable', 'string'],
            'type' => ['required', 'in:client,prospect'],
            'notes' => ['nullable', 'string'],
        ]);
    }
}