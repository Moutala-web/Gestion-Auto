<?php

namespace App\Services;

use App\Models\Location;
use App\Models\Recu;
use App\Models\Vehicule;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LocationService
{
    public function __construct(private CaisseService $caisse)
    {
    }

    /**
     * Crée une réservation. Le véhicule est verrouillé pendant la vérification :
     * deux réservations simultanées ne peuvent pas se chevaucher.
     */
    public function creer(array $data, int $userId): Location
    {
        return DB::transaction(function () use ($data, $userId) {
            $vehicule = Vehicule::whereKey($data['vehicule_id'])->lockForUpdate()->firstOrFail();

            if (in_array($vehicule->statut, ['vendu', 'maintenance'], true)) {
                $this->erreur('vehicule_id', "Ce véhicule n'est pas louable (statut : {$vehicule->statut}).");
            }

            $debut = Carbon::parse($data['date_depart']);
            $fin = Carbon::parse($data['date_retour_prevue']);

            $conflit = Location::chevauche($vehicule->id, $debut, $fin)->first();

            if ($conflit) {
                $this->erreur('date_depart', sprintf(
                    'Ce véhicule est déjà pris du %s au %s (location %s).',
                    $conflit->date_depart->format('d/m/Y H:i'),
                    $conflit->date_retour_prevue->format('d/m/Y H:i'),
                    $conflit->numero
                ));
            }

            $location = Location::create([
                'numero' => 'TMP-' . Str::uuid(),
                'client_id' => $data['client_id'],
                'vehicule_id' => $vehicule->id,
                'user_id' => $userId,
                'date_depart' => $debut,
                'date_retour_prevue' => $fin,
                'tarif' => round((float) $data['tarif'], 2),
                'caution' => round((float) ($data['caution'] ?? 0), 2),
                'kilometrage_depart' => $data['kilometrage_depart'] ?? $vehicule->kilometrage,
                'etat_depart' => $data['etat_depart'] ?? null,
                'notes' => $data['notes'] ?? null,
                'statut' => 'reservee',
            ]);
            $location->update(['numero' => sprintf('L-%05d', $location->id)]);

            return $location;
        });
    }

    /** Remise du véhicule au client : la location passe « en cours », le véhicule « en location ». */
    public function demarrer(Location $location, array $data): Location
    {
        return DB::transaction(function () use ($location, $data) {
            // Ordre de verrouillage fixe : véhicule, puis location
            $vehicule = Vehicule::whereKey($location->vehicule_id)->lockForUpdate()->firstOrFail();
            $location = Location::whereKey($location->id)->lockForUpdate()->firstOrFail();

            if ($location->statut !== 'reservee') {
                $this->erreur('statut', 'Seule une location réservée peut être démarrée.');
            }

            if (! in_array($vehicule->statut, ['disponible', 'reserve'], true)) {
                $this->erreur('statut', "Le véhicule n'est pas disponible (statut : {$vehicule->statut}). S'il est encore chez un autre client, enregistre d'abord son retour.");
            }

            $vehicule->update(['statut' => 'en_location']);

            $location->update([
                'statut' => 'en_cours',
                'kilometrage_depart' => $data['kilometrage_depart'],
                'etat_depart' => $data['etat_depart'] ?? $location->etat_depart,
            ]);

            return $location;
        });
    }

    /**
     * Encaisse un paiement de location ou de caution, sans dépasser le montant convenu.
     * Un reçu est créé pour chaque paiement.
     */
    public function encaisser(Location $location, array $data, int $userId): Location
    {
        return DB::transaction(function () use ($location, $data, $userId) {
            $location = Location::whereKey($location->id)->lockForUpdate()->firstOrFail();

            if (! in_array($location->statut, ['reservee', 'en_cours'], true)) {
                $this->erreur('montant', 'Cette location est clôturée : plus aucun paiement possible.');
            }

            $type = $data['type']; // « location » ou « caution »
            $montant = round((float) $data['montant'], 2);

            $deja = (float) $location->paiements()
                ->where('type', $type)->where('statut', 'valide')->sum('montant');
            $plafond = $type === 'location' ? (float) $location->tarif : (float) $location->caution;
            $reste = round($plafond - $deja, 2);

            if ($montant > $reste + 0.001) {
                $this->erreur('montant', sprintf(
                    'Montant trop élevé : il reste %s FCFA à encaisser pour %s.',
                    number_format(max($reste, 0), 0, ',', ' '),
                    $type === 'location' ? 'la location' : 'la caution'
                ));
            }

            $paiement = $location->paiements()->create([
                'type' => $type,
                'mode' => $data['mode'],
                'montant' => $montant,
                'reference_transaction' => $data['reference_transaction'] ?? null,
                'date_paiement' => now(),
                'statut' => 'valide',
            ]);

            // « location » compte dans le chiffre d'affaires ; « caution » n'y compte jamais
            $this->caisse->entree(
                categorie: $type,
                montant: $montant,
                mode: $data['mode'],
                userId: $userId,
                refType: 'location',
                refId: $location->id,
                description: ($type === 'caution' ? 'Caution' : 'Paiement') . ' — location ' . $location->numero,
            );

            $recu = Recu::create([
                'numero' => 'TMP-' . Str::uuid(),
                'location_id' => $location->id,
                'paiement_id' => $paiement->id,
                'date_generation' => now(),
            ]);
            $recu->update(['numero' => sprintf('R-%05d', $recu->id)]);

            return $location;
        });
    }

    /** Retour du véhicule : clôture, retenue éventuelle sur la caution, remboursement du reste. */
    public function terminer(Location $location, array $data, int $userId): Location
    {
        return DB::transaction(function () use ($location, $data, $userId) {
            $vehicule = Vehicule::whereKey($location->vehicule_id)->lockForUpdate()->firstOrFail();
            $location = Location::whereKey($location->id)->lockForUpdate()->firstOrFail();

            if ($location->statut !== 'en_cours') {
                $this->erreur('statut', 'Seule une location en cours peut être clôturée.');
            }

            $kmRetour = (int) $data['kilometrage_retour'];

            if ($kmRetour < $location->kilometrage_depart) {
                $this->erreur('kilometrage_retour', "Le kilométrage de retour ne peut pas être inférieur à celui du départ ({$location->kilometrage_depart} km).");
            }

            $payeLocation = (float) $location->paiements()
                ->where('type', 'location')->where('statut', 'valide')->sum('montant');
            $resteTarif = round((float) $location->tarif - $payeLocation, 2);

            if ($resteTarif > 0.001) {
                $this->erreur('tarif', 'Le tarif doit être entièrement payé avant le retour : il reste ' . number_format($resteTarif, 0, ',', ' ') . ' FCFA à encaisser.');
            }

            $versee = (float) $location->paiements()
                ->where('type', 'caution')->where('statut', 'valide')->sum('montant');
            $retenue = round((float) ($data['retenue_caution'] ?? 0), 2);

            if ($retenue > $versee + 0.001) {
                $this->erreur('retenue_caution', 'La retenue ne peut pas dépasser la caution versée (' . number_format($versee, 0, ',', ' ') . ' FCFA).');
            }

            $rembourse = round($versee - $retenue, 2);

            if ($rembourse > 0) {
                $this->caisse->sortie(
                    categorie: 'remboursement_caution',
                    montant: $rembourse,
                    mode: $data['mode_remboursement'],
                    userId: $userId,
                    refType: 'location',
                    refId: $location->id,
                    description: 'Remboursement de la caution — location ' . $location->numero,
                );
            }

            $vehicule->update([
                'statut' => 'disponible',
                'kilometrage' => max($vehicule->kilometrage, $kmRetour),
            ]);

            $location->update([
                'statut' => 'terminee',
                'date_retour_reelle' => now(),
                'kilometrage_retour' => $kmRetour,
                'etat_retour' => $data['etat_retour'] ?? null,
                'retenue_caution' => $retenue,
                'caution_remboursee' => $rembourse,
            ]);

            return $location;
        });
    }

    /** Annule une réservation (avant le départ) et rembourse tout ce qui a été encaissé. */
    public function annuler(Location $location, string $motif, int $userId): Location
    {
        return DB::transaction(function () use ($location, $motif, $userId) {
            $location = Location::whereKey($location->id)->lockForUpdate()->firstOrFail();

            if ($location->statut !== 'reservee') {
                $this->erreur('motif', 'Seule une location réservée peut être annulée.');
            }

            // Les paiements sont annulés, jamais supprimés
            foreach ($location->paiements()->where('statut', 'valide')->get() as $paiement) {
                $paiement->update(['statut' => 'annule']);

                $this->caisse->sortie(
                    categorie: $paiement->type === 'caution' ? 'remboursement_caution' : 'annulation_location',
                    montant: (float) $paiement->montant,
                    mode: $paiement->mode,
                    userId: $userId,
                    refType: 'location',
                    refId: $location->id,
                    description: 'Annulation de la location ' . $location->numero,
                );
            }

            $location->update([
                'statut' => 'annulee',
                'notes' => trim(($location->notes ? $location->notes . "\n" : '') . 'Annulée : ' . $motif),
            ]);

            return $location;
        });
    }

    private function erreur(string $champ, string $message): never
    {
        throw ValidationException::withMessages([$champ => $message]);
    }
}