<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Moto;
use App\Models\Piece;
use App\Models\Vehicule;
use App\Models\Vente;
use App\Models\VenteDetail;
use App\Services\VenteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VenteController extends Controller
{
    // Prestations proposées au départ, avant que l'entreprise n'ait enregistré les siennes
    private const SERVICES_PAR_DEFAUT = [
        'Dédouanement',
        'Transport et acheminement',
        'Carte grise et formalités',
        'Import à la demande (commission)',
        'Réparation et mécanique',
    ];

    public function index(Request $request): View
    {
        $recherche = trim((string) $request->query('q', ''));
        $statut = $request->query('statut');

        $ventes = Vente::query()
            ->with('client')
            ->withCount('details')
            ->when($recherche !== '', function ($query) use ($recherche) {
                $query->where(function ($q) use ($recherche) {
                    $q->where('numero', 'like', "%{$recherche}%")
                        ->orWhereHas('client', function ($c) use ($recherche) {
                            $c->where('nom', 'like', "%{$recherche}%")
                                ->orWhere('prenom', 'like', "%{$recherche}%")
                                ->orWhere('telephone', 'like', "%{$recherche}%");
                        });
                });
            })
            ->when($statut, fn ($query) => $query->where('statut', $statut))
            ->orderByDesc('date_vente')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        // Articles proposés dans la fenêtre « Nouvelle vente » : seulement ceux qu'on peut vendre
        $enStock = fn (string $famille) => Piece::where('famille', $famille)
            ->where('quantite_stock', '>', 0)
            ->orderBy('designation')->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'label' => "{$p->designation} ({$p->reference}) - {$p->quantite_stock} en stock",
                'prix' => (float) $p->prix_vente,
                'stock' => $p->quantite_stock,
            ])->values()->all();

        // Véhicules et motos : une fiche par unité, regroupées par modèle dans le formulaire
        $enFiches = fn (string $classe) => $classe::whereIn('statut', ['disponible', 'reserve'])
            ->orderBy('marque')->orderBy('modele')->orderBy('reference')->get()
            ->map(fn ($u) => [
                'id' => $u->id,
                'groupe' => trim("{$u->marque} {$u->modele}"),
                'label' => implode(' · ', array_filter([
                    $u->reference,
                    $u->numero_chassis ? "châssis {$u->numero_chassis}" : 'châssis à compléter',
                    $u->couleur,
                    $u->statut === 'reserve' ? 'réservé' : null,
                ])),
                'prix' => (float) ($u->prix_vente ?? 0),
                'stock' => 1,
            ])->values()->all();

        $articles = [
            'vehicule' => $enFiches(Vehicule::class),
            'moto' => $enFiches(Moto::class),
            'piece' => $enStock('piece'),
            'divers' => $enStock('divers'),
        ];

        // Suggestions de prestations : celles déjà vendues (avec leur dernier prix), puis les prestations par défaut
        $services = VenteDetail::where('type_article', 'service')
            ->orderByDesc('id')
            ->limit(300)
            ->get(['designation', 'prix_unitaire'])
            ->unique('designation')
            ->map(fn ($d) => ['nom' => $d->designation, 'prix' => (float) $d->prix_unitaire])
            ->values();

        foreach (self::SERVICES_PAR_DEFAUT as $nom) {
            if (! $services->contains('nom', $nom)) {
                $services->push(['nom' => $nom, 'prix' => 0]);
            }
        }

        $services = $services->values()->all();

        $clients = Client::orderBy('nom')->orderBy('prenom')->get();

        // Le bouton « Nouvelle prestation » de la page Prestations ouvre directement la fenêtre de vente
        $nouveauService = $request->query('nouveau') === 'service';

        return view('ventes.index', compact('ventes', 'recherche', 'statut', 'articles', 'services', 'clients', 'nouveauService'));
    }

    // Les articles divers sont enregistrés comme des pièces (type « piece ») : même stock, même logique
    public function store(Request $request, VenteService $service): RedirectResponse
    {
        $data = $request->validate([
            'client_id' => ['required', 'exists:clients,id'],
            'mode_paiement' => ['required', 'in:especes,orange_money'],
            'reference_transaction' => ['nullable', 'required_if:mode_paiement,orange_money', 'string', 'max:100'],
            'remise' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
            'lignes' => ['required', 'array', 'min:1'],
            'lignes.*.type' => ['required', 'in:vehicule,moto,piece,service'],
            'lignes.*.id' => ['nullable', 'integer'],
            'lignes.*.designation' => ['nullable', 'string', 'max:255'],
            'lignes.*.quantite' => ['required', 'integer', 'min:1'],
            'lignes.*.prix_unitaire' => ['required', 'numeric', 'min:0'],
        ], [
            'client_id.required' => 'Choisis un client.',
            'lignes.required' => 'Ajoute au moins un article ou une prestation.',
            'reference_transaction.required_if' => 'La référence de la transaction Orange Money est obligatoire.',
        ]);

        $vente = $service->creer($data, $request->user()->id);

        return redirect()->route('ventes.show', $vente)
            ->with('succes', "Vente {$vente->numero} enregistrée.");
    }

    public function show(Vente $vente): View
    {
        $vente->load(['client', 'user', 'details.vehicule', 'details.moto', 'details.piece', 'paiements', 'recus']);

        return view('ventes.show', compact('vente'));
    }

    public function annuler(Request $request, Vente $vente, VenteService $service): RedirectResponse
    {
        $data = $request->validate([
            'motif' => ['required', 'string', 'max:255'],
        ], [
            'motif.required' => 'Indique le motif de l\'annulation.',
        ]);

        $service->annuler($vente, $data['motif'], $request->user()->id);

        return redirect()->route('ventes.show', $vente)
            ->with('succes', "Vente {$vente->numero} annulée.");
    }
}