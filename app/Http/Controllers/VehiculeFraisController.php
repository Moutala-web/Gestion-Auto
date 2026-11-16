<?php

namespace App\Http\Controllers;

use App\Models\MouvementCaisse;
use App\Models\Vehicule;
use App\Models\VehiculeFrais;
use App\Services\CaisseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VehiculeFraisController extends Controller
{
    public function __construct(private CaisseService $caisse)
    {
    }

    public function store(Request $request, Vehicule $vehicule): RedirectResponse
    {
        // Un frais ajouté après la vente fausserait la marge
        if ($vehicule->statut === 'vendu') {
            return redirect()->route('vehicules.show', $vehicule)
                ->with('erreur', 'Ce véhicule est vendu : on ne peut plus ajouter de frais.');
        }

        $data = $request->validate([
            'libelle' => ['required', 'string', 'max:150'],
            'categorie' => ['required', 'in:transport,douane,reparation,entretien,lavage,papiers,autres'],
            'montant' => ['required', 'numeric', 'min:0.01'],
            'mode_paiement' => ['required', 'in:especes,orange_money'],
            'date_frais' => ['required', 'date'],
            'description' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($data, $vehicule, $request) {
            $frais = $vehicule->frais()->create($data);

            // Le frais est une sortie d'argent : il est enregistré en caisse
            $this->caisse->sortie(
                categorie: 'frais_vehicule',
                montant: (float) $frais->montant,
                mode: $frais->mode_paiement,
                userId: $request->user()->id,
                refType: 'vehicule_frais',
                refId: $frais->id,
                description: "Frais {$vehicule->reference} : {$frais->libelle}",
                date: $frais->date_frais->copy()->setTimeFrom(now()),
            );
        });

        return redirect()->route('vehicules.show', $vehicule)->with('succes', 'Frais ajouté et enregistré en caisse.');
    }

    public function destroy(Request $request, Vehicule $vehicule, VehiculeFrais $frais): RedirectResponse
    {
        // Le frais doit bien appartenir à ce véhicule
        abort_unless($frais->vehicule_id === $vehicule->id, 404);

        if ($vehicule->statut === 'vendu') {
            return redirect()->route('vehicules.show', $vehicule)
                ->with('erreur', 'Ce véhicule est vendu : on ne peut plus modifier ses frais.');
        }

        DB::transaction(function () use ($vehicule, $frais, $request) {
            // Seuls les frais qui sont sortis de la caisse ont une contrepartie à enregistrer
            $sortie = MouvementCaisse::where('reference_type', 'vehicule_frais')
                ->where('reference_id', $frais->id)
                ->where('categorie', 'frais_vehicule')
                ->exists();

            if ($sortie) {
                $this->caisse->entree(
                    categorie: 'annulation_frais',
                    montant: (float) $frais->montant,
                    mode: $frais->mode_paiement,
                    userId: $request->user()->id,
                    refType: 'vehicule_frais',
                    refId: $frais->id,
                    description: "Annulation du frais {$vehicule->reference} : {$frais->libelle}",
                );
            }

            $frais->delete();
        });

        return redirect()->route('vehicules.show', $vehicule)->with('succes', 'Frais supprimé.');
    }
}