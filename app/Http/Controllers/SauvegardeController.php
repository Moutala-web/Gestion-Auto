<?php

namespace App\Http\Controllers;

use App\Services\SauvegardeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SauvegardeController extends Controller
{
    public function index(SauvegardeService $service): View
    {
        $sauvegardes = $service->lister();
        $derniere = $sauvegardes[0]['date'] ?? null;

        return view('sauvegardes.index', compact('sauvegardes', 'derniere'));
    }

    public function store(SauvegardeService $service): RedirectResponse
    {
        try {
            $nom = $service->creer();
        } catch (\Throwable $e) {
            return back()->with('erreur', 'La sauvegarde a échoué : ' . $e->getMessage());
        }

        return back()->with('succes', "Sauvegarde créée : {$nom}");
    }

    public function telecharger(SauvegardeService $service, string $fichier): BinaryFileResponse
    {
        $chemin = $service->chemin($fichier);

        abort_if($chemin === null, 404);

        return response()->download($chemin, $fichier);
    }

    public function destroy(SauvegardeService $service, string $fichier): RedirectResponse
    {
        abort_unless($service->supprimer($fichier), 404);

        return back()->with('succes', 'Sauvegarde supprimée.');
    }
}