<?php

namespace App\Providers;

use App\Models\Achat;
use App\Models\Depense;
use App\Models\Location;
use App\Models\Vente;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->cloche();
        $this->evenements();
    }

    // Données de la cloche : alertes mises à jour, notifications non lues et dernières notifications
    private function cloche(): void
    {
        View::composer('partials.cloche', function ($view) {
            $utilisateur = auth()->user();

            if (! $utilisateur) {
                return;
            }

            // Les alertes sont recalculées au plus toutes les 20 secondes
            if (Cache::add('notifications.synchro', true, 20)) {
                app(NotificationService::class)->synchroniser();
            }

            $view->with([
                'nonLues' => $utilisateur->unreadNotifications()->count(),
                'notifications' => $utilisateur->notifications()->latest()->limit(8)->get(),
            ]);
        });
    }

    // Notifications d'événements : déclenchées par les changements dans les données
    private function evenements(): void
    {
        $fmt = fn ($n) => number_format((float) $n, 0, ',', ' ') . ' FCFA';
        $notifier = fn () => app(NotificationService::class);

        Vente::updated(function (Vente $vente) use ($fmt, $notifier) {
            // Le numéro définitif (V-00012) remplace le numéro provisoire : la vente vient d'être enregistrée
            if ($vente->wasChanged('numero') && str_starts_with((string) $vente->getOriginal('numero'), 'TMP-')) {
                $notifier()->evenement('success', 'cart', 'Vente enregistrée', "{$vente->numero} : {$fmt($vente->total)}", route('ventes.show', $vente, false));
            }

            if ($vente->wasChanged('statut') && $vente->statut === 'annulee') {
                $notifier()->evenement('warning', 'warning', 'Vente annulée', "{$vente->numero} : {$fmt($vente->total)}", route('ventes.show', $vente, false));
            }
        });

        Location::updated(function (Location $location) use ($notifier) {
            $location->loadMissing(['vehicule', 'client']);

            $detail = "{$location->numero} · {$location->client->nom_complet} · {$location->vehicule->marque} {$location->vehicule->modele}";
            $url = route('locations.show', $location, false);

            if ($location->wasChanged('numero') && str_starts_with((string) $location->getOriginal('numero'), 'TMP-')) {
                $notifier()->evenement('info', 'key', 'Réservation enregistrée', $detail, $url);
            }

            if ($location->wasChanged('statut')) {
                match ($location->statut) {
                    'en_cours' => $notifier()->evenement('info', 'key', 'Location démarrée', $detail, $url),
                    'terminee' => $notifier()->evenement('success', 'check-circle', 'Location terminée', $detail, $url),
                    'annulee' => $notifier()->evenement('warning', 'warning', 'Location annulée', $detail, $url),
                    default => null,
                };
            }
        });

        Achat::updated(function (Achat $achat) use ($fmt, $notifier) {
            if ($achat->wasChanged('statut') && $achat->statut === 'annule') {
                $notifier()->evenement('warning', 'inbox-in', 'Achat annulé', "{$achat->numero} : {$fmt($achat->montant)}", route('achats.index', [], false));
            }
        });

        Depense::updated(function (Depense $depense) use ($fmt, $notifier) {
            if ($depense->wasChanged('statut') && $depense->statut === 'annulee') {
                $notifier()->evenement('warning', 'receipt', 'Dépense annulée', "{$depense->libelle} : {$fmt($depense->montant)}", route('depenses.index', [], false));
            }
        });
    }
}