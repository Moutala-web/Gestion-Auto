<?php

namespace App\Http\Controllers;

use App\Models\VenteDetail;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ServiceDiversController extends Controller
{
    public function index(Request $request): View
    {
        $recherche = trim((string) $request->query('q', ''));

        // Par défaut : le mois en cours. Si on vide les dates, on voit tout l'historique.
        $du = $request->has('du') ? ($request->query('du') ?: null) : now()->startOfMonth()->toDateString();
        $au = $request->has('au') ? ($request->query('au') ?: null) : now()->toDateString();

        $base = VenteDetail::query()
            ->where('type_article', 'service')
            ->when($recherche !== '', fn ($q) => $q->where('designation', 'like', "%{$recherche}%"))
            ->whereHas('vente', function ($vente) use ($du, $au) {
                $vente
                    ->when($du, fn ($q) => $q->where('date_vente', '>=', Carbon::parse($du)->startOfDay()))
                    ->when($au, fn ($q) => $q->where('date_vente', '<=', Carbon::parse($au)->endOfDay()));
            });

        // Les totaux ne comptent que les ventes validées
        $valides = (clone $base)->whereHas('vente', fn ($vente) => $vente->where('statut', 'validee'));

        $total = (float) (clone $valides)->sum('total');
        $nombre = (clone $valides)->count();

        $parService = (clone $valides)
            ->select('designation', DB::raw('SUM(quantite) as quantite'), DB::raw('SUM(total) as montant'))
            ->groupBy('designation')
            ->orderByDesc('montant')
            ->get();

        $lignes = (clone $base)
            ->with('vente.client')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('services.index', compact('lignes', 'recherche', 'du', 'au', 'total', 'nombre', 'parService'));
    }
}