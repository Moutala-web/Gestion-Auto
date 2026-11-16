<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Location;
use App\Models\Moto;
use App\Models\MouvementCaisse;
use App\Models\Piece;
use App\Models\Vehicule;
use App\Models\Vente;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $debutMois = now()->startOfMonth();
        $moisPrecedent = now()->startOfMonth()->subMonthNoOverflow();

        // ---------- Argent ----------
        $soldes = [
            'total' => MouvementCaisse::solde(),
            'especes' => MouvementCaisse::solde('especes'),
            'orange_money' => MouvementCaisse::solde('orange_money'),
        ];

        $caMois = MouvementCaisse::chiffreAffaires($debutMois->toDateString(), now()->toDateString());
        $caPrecedent = MouvementCaisse::chiffreAffaires(
            $moisPrecedent->toDateString(),
            $moisPrecedent->copy()->endOfMonth()->toDateString()
        );
        // Variation en % par rapport au mois dernier (null s'il n'y a rien à comparer)
        $variation = $caPrecedent > 0 ? (int) round((($caMois - $caPrecedent) / $caPrecedent) * 100) : null;

        // Dépenses du mois : achats + frais de véhicules + fonctionnement, annulations déduites
        $depenses = MouvementCaisse::totauxDepenses($debutMois->toDateString(), now()->toDateString());

        // Valeur du stock au prix d'achat : véhicules et motos non vendus + pièces en stock
        $valeurStock = (float) Vehicule::whereIn('statut', ['disponible', 'reserve', 'maintenance'])->sum('prix_achat')
            + (float) Moto::whereIn('statut', ['disponible', 'reserve'])->sum('prix_achat')
            + (float) Piece::sum(DB::raw('prix_achat * quantite_stock'));

        // ---------- Activité ----------
        $stats = [
            'vehicules' => Vehicule::where('statut', 'disponible')->count(),
            'motos' => Moto::where('statut', 'disponible')->count(),
            'pieces_alerte' => Piece::enAlerte()->count(),
            'clients' => Client::count(),
            'locations_en_cours' => Location::where('statut', 'en_cours')->count(),
            'locations_en_retard' => Location::where('statut', 'en_cours')->where('date_retour_prevue', '<', now())->count(),
        ];

        // ---------- Graphique : chiffre d'affaires des 6 derniers mois ----------
        $mois = collect(range(5, 0))->map(function (int $i) {
            $debut = now()->startOfMonth()->subMonthsNoOverflow($i);

            return [
                'label' => $debut->translatedFormat('M'),
                'ca' => MouvementCaisse::chiffreAffaires($debut->toDateString(), $debut->copy()->endOfMonth()->toDateString()),
                'courant' => $i === 0,
            ];
        });
        $maxCa = max((float) $mois->max('ca'), 1);

        // ---------- Listes ----------
        $locations = Location::with(['client', 'vehicule'])
            ->whereIn('statut', ['en_cours', 'reservee'])
            ->orderByRaw("statut = 'en_cours' desc")
            ->orderBy('date_retour_prevue')
            ->limit(5)
            ->get();

        $ventes = Vente::with('client')
            ->orderByDesc('date_vente')
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        $mouvements = MouvementCaisse::orderByDesc('date_operation')
            ->orderByDesc('id')
            ->limit(6)
            ->get();

        $piecesEnAlerte = Piece::enAlerte()->orderBy('quantite_stock')->limit(5)->get();

        return view('dashboard', compact(
            'soldes', 'caMois', 'variation', 'depenses', 'valeurStock',
            'stats', 'mois', 'maxCa', 'locations', 'ventes', 'mouvements', 'piecesEnAlerte'
        ));
    }
}