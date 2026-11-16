<?php

namespace App\Http\Controllers;

use App\Models\Parametre;
use App\Models\Recu;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class RecuController extends Controller
{
    // Le PDF est généré à la demande : il se régénère toujours à l'identique à partir des données
    public function pdf(Request $request, Recu $recu)
    {
        $recu->load([
            'paiement',
            'vente.client',
            'vente.details',
            'location.client',
            'location.vehicule',
            'location.paiements',
        ]);

        $parametre = Parametre::actuel();
        $logo = $this->logo($parametre);

        $pdf = Pdf::loadView('recus.pdf', compact('recu', 'parametre', 'logo'))
            ->setPaper('a5', 'portrait');

        $nom = 'Recu-' . $recu->numero . '.pdf';

        // ?telecharger=1 force le téléchargement, sinon le PDF s'ouvre dans le navigateur
        return $request->boolean('telecharger') ? $pdf->download($nom) : $pdf->stream($nom);
    }

    // Le logo est intégré au PDF en base64 : aucun accès réseau ni chemin à résoudre
    private function logo(Parametre $parametre): ?string
    {
        if (! $parametre->logo) {
            return null;
        }

        $chemin = public_path($parametre->logo);

        if (! is_file($chemin)) {
            return null;
        }

        $extension = strtolower(pathinfo($chemin, PATHINFO_EXTENSION));
        $type = $extension === 'jpg' ? 'jpeg' : $extension;

        return 'data:image/' . $type . ';base64,' . base64_encode(file_get_contents($chemin));
    }
}