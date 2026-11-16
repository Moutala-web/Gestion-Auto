<?php

namespace App\Http\Controllers;

use App\Models\MouvementCaisse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CaisseController extends Controller
{
    public function index(Request $request): View
    {
        [$du, $au, $type, $categorie, $mode] = $this->filtres($request);

        $mouvements = $this->requete($du, $au, $type, $categorie, $mode)
            ->with('user')
            ->paginate(20)
            ->withQueryString();

        // Soldes réels : toujours calculés sur tout l'historique
        $soldes = [
            'total' => MouvementCaisse::solde(),
            'especes' => MouvementCaisse::solde('especes'),
            'orange_money' => MouvementCaisse::solde('orange_money'),
        ];

        // Chiffres de la période choisie
        $periode = [
            'entrees' => (float) MouvementCaisse::entrees()->entre($du, $au)->sum('montant'),
            'sorties' => (float) MouvementCaisse::sorties()->entre($du, $au)->sum('montant'),
            'ca' => MouvementCaisse::chiffreAffaires($du, $au),
        ];

        return view('caisse.index', compact('mouvements', 'soldes', 'periode', 'du', 'au', 'type', 'categorie', 'mode'));
    }

    // Export CSV du journal de caisse, avec les mêmes filtres que la page
    public function exporter(Request $request): StreamedResponse
    {
        [$du, $au, $type, $categorie, $mode] = $this->filtres($request);

        $requete = $this->requete($du, $au, $type, $categorie, $mode)->with('user');

        $nom = 'caisse-' . ($du ?: 'debut') . '-au-' . ($au ?: 'ce-jour') . '.csv';

        $modes = ['especes' => 'Espèces', 'orange_money' => 'Orange Money'];
        $libelles = MouvementCaisse::LIBELLES;

        return response()->streamDownload(function () use ($requete, $modes, $libelles) {
            $sortie = fopen('php://output', 'w');

            // BOM UTF-8 : Excel affiche correctement les accents
            fwrite($sortie, "\xEF\xBB\xBF");

            fputcsv($sortie, ['Date', 'Heure', 'Sens', 'Nature', 'Mode de paiement', 'Montant (FCFA)', 'Description', 'Utilisateur'], ';');

            foreach ($requete->lazy(500) as $m) {
                $date = Carbon::parse($m->date_operation);
                $montant = (float) $m->montant;

                fputcsv($sortie, [
                    $date->format('d/m/Y'),
                    $date->format('H:i'),
                    $m->type === 'entree' ? 'Entrée' : 'Sortie',
                    $libelles[$m->categorie] ?? $m->categorie,
                    $modes[$m->mode_paiement] ?? $m->mode_paiement,
                    // Les sorties sont en négatif : la somme de la colonne donne directement le solde
                    number_format($m->type === 'entree' ? $montant : -$montant, 2, ',', ''),
                    $this->proteger((string) $m->description),
                    $m->user?->name ?? '',
                ], ';');
            }

            fclose($sortie);
        }, $nom, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    // Filtres de la page : par défaut le mois en cours, dates vides = tout l'historique
    private function filtres(Request $request): array
    {
        $du = $request->has('du') ? ($request->query('du') ?: null) : now()->startOfMonth()->toDateString();
        $au = $request->has('au') ? ($request->query('au') ?: null) : now()->toDateString();

        return [$du, $au, $request->query('type'), $request->query('categorie'), $request->query('mode')];
    }

    private function requete(?string $du, ?string $au, ?string $type, ?string $categorie, ?string $mode)
    {
        return MouvementCaisse::query()
            ->entre($du, $au)
            ->when($type, fn ($q) => $q->where('type', $type))
            ->when($categorie, fn ($q) => $q->where('categorie', $categorie))
            ->when($mode, fn ($q) => $q->where('mode_paiement', $mode))
            ->orderByDesc('date_operation')
            ->orderByDesc('id');
    }

    // Un texte qui commence par = + - @ serait pris pour une formule par Excel
    private function proteger(string $texte): string
    {
        return preg_match('/^[=+\-@]/', $texte) ? "'" . $texte : $texte;
    }
}