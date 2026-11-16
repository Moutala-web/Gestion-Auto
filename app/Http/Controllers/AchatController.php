<?php

namespace App\Http\Controllers;

use App\Models\Achat;
use App\Models\Modele;
use App\Models\Moto;
use App\Models\Piece;
use App\Models\Vehicule;
use App\Services\AchatService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AchatController extends Controller
{
    public function index(Request $request): View
    {
        $recherche = trim((string) $request->query('q', ''));
        $type = $request->query('type');

        // Par défaut : le mois en cours. Si on vide les dates, on voit tout l'historique.
        $du = $request->has('du') ? ($request->query('du') ?: null) : now()->startOfMonth()->toDateString();
        $au = $request->has('au') ? ($request->query('au') ?: null) : now()->toDateString();

        $base = Achat::query()
            ->when($recherche !== '', function ($query) use ($recherche) {
                $query->where(function ($q) use ($recherche) {
                    $q->where('fournisseur', 'like', "%{$recherche}%")
                        ->orWhere('reference_transaction', 'like', "%{$recherche}%")
                        ->orWhere('notes', 'like', "%{$recherche}%");
                });
            })
            // Les articles divers sont des pièces d'une famille particulière
            ->when($type === 'divers', fn ($q) => $q->where('type_article', 'piece')
                ->whereHas('piece', fn ($p) => $p->where('famille', 'divers')))
            ->when($type === 'piece', fn ($q) => $q->where('type_article', 'piece')
                ->whereHas('piece', fn ($p) => $p->where('famille', 'piece')))
            ->when(in_array($type, ['vehicule', 'moto'], true), fn ($q) => $q->where('type_article', $type))
            ->when($du, fn ($q) => $q->where('date_achat', '>=', Carbon::parse($du)->startOfDay()))
            ->when($au, fn ($q) => $q->where('date_achat', '<=', Carbon::parse($au)->endOfDay()));

        $total = (float) (clone $base)->valides()->sum('montant');
        $nombre = (clone $base)->valides()->count();

        $achats = (clone $base)
            ->with(['vehicule', 'moto', 'piece', 'user'])
            ->orderByDesc('date_achat')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        // Ce qu'on peut acheter : uniquement les éléments définis dans le catalogue
        $pieces = Piece::whereNotNull('modele_id')->get()->keyBy('modele_id');

        $modelesAchat = [
            'vehicule' => $this->listeFiches('voiture', Vehicule::class),
            'moto' => $this->listeFiches('moto', Moto::class),
            'piece' => $this->listeStock('piece', $pieces),
            'divers' => $this->listeStock('divers', $pieces),
        ];

        // Ouverture directe de la fenêtre d'achat : depuis le catalogue (?modele=) ou un bouton « Acheter » (?acheter=)
        $preselection = null;
        $conversion = ['voiture' => 'vehicule', 'moto' => 'moto', 'piece' => 'piece', 'divers' => 'divers'];

        if ($request->filled('modele') && ($modele = Modele::find($request->query('modele')))) {
            $preselection = ['type' => $conversion[$modele->type], 'modele_id' => $modele->id];
        } elseif (in_array($request->query('acheter'), ['vehicule', 'moto', 'piece', 'divers'], true)) {
            $preselection = ['type' => $request->query('acheter'), 'modele_id' => ''];
        }

        return view('achats.index', compact('achats', 'recherche', 'type', 'du', 'au', 'total', 'nombre', 'modelesAchat', 'preselection'));
    }

    public function annuler(Request $request, Achat $achat, AchatService $service): RedirectResponse
    {
        $data = $request->validate([
            'motif' => ['required', 'string', 'max:255'],
        ], [
            'motif.required' => 'Indique le motif de l\'annulation.',
        ]);

        $service->annuler($achat, $data['motif'], $request->user()->id);

        return back()->with('succes', "Achat {$achat->numero} annulé.");
    }

    // Voitures ou motos du catalogue, avec le dernier prix d'achat connu pour chaque modèle
    private function listeFiches(string $type, string $classe): array
    {
        $dernierPrix = $classe::query()
            ->whereNotNull('modele_id')
            ->orderByDesc('id')
            ->get(['modele_id', 'prix_achat'])
            ->unique('modele_id')
            ->pluck('prix_achat', 'modele_id');

        return Modele::where('type', $type)
            ->orderBy('marque')->orderBy('modele')->get()
            ->map(fn ($m) => [
                'id' => $m->id,
                'label' => $m->nom_complet . ($m->categorie ? ' · ' . $m->categorie : ''),
                'prix' => (float) ($dernierPrix[$m->id] ?? 0),
            ])->values()->all();
    }

    // Pièces ou articles divers du catalogue, avec leur stock actuel et leur prix d'achat moyen
    private function listeStock(string $type, $pieces): array
    {
        return Modele::where('type', $type)
            ->orderBy('modele')->get()
            ->map(function ($m) use ($pieces) {
                $piece = $pieces[$m->id] ?? null;

                return [
                    'id' => $m->id,
                    'label' => $m->nom_complet . ($m->categorie ? ' · ' . $m->categorie : '') . ' — ' . ($piece?->quantite_stock ?? 0) . ' en stock',
                    'prix' => (float) ($piece?->prix_achat ?? 0),
                ];
            })->values()->all();
    }
}