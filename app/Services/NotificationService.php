<?php

namespace App\Services;

use App\Models\Location;
use App\Models\Piece;
use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class NotificationService
{
    /**
     * Événement ponctuel (vente annulée, location terminée...) :
     * tous les utilisateurs actifs reçoivent la notification.
     */
    public function evenement(string $niveau, string $icone, string $titre, string $message, ?string $url = null): void
    {
        foreach ($this->utilisateurs() as $utilisateur) {
            $this->creer($utilisateur, 'evenement', [
                'niveau' => $niveau,
                'icone' => $icone,
                'titre' => $titre,
                'message' => $message,
                'url' => $url,
            ]);
        }
    }

    /**
     * Met les alertes à jour : crée celles qui viennent d'apparaître,
     * retire celles dont la situation est réglée.
     */
    public function synchroniser(): void
    {
        $actives = $this->alertesActives();

        foreach ($this->utilisateurs() as $utilisateur) {
            $existantes = $utilisateur->notifications()->where('type', 'alerte')->get();
            $cles = $existantes->map(fn ($n) => $n->data['cle'] ?? null)->filter()->all();

            foreach ($actives as $cle => $data) {
                if (! in_array($cle, $cles, true)) {
                    $this->creer($utilisateur, 'alerte', $data + ['cle' => $cle]);
                }
            }

            foreach ($existantes as $notification) {
                if (! isset($actives[$notification->data['cle'] ?? ''])) {
                    $notification->delete();
                }
            }
        }

        // Nettoyage : les événements de plus de 60 jours disparaissent
        DatabaseNotification::where('type', 'evenement')
            ->where('created_at', '<', now()->subDays(60))
            ->delete();
    }

    /** Toutes les alertes qui sont d'actualité en ce moment, indexées par une clé unique. */
    private function alertesActives(): array
    {
        $alertes = [];
        $maintenant = now();
        $dans24h = now()->addDay();

        $locations = Location::with(['client', 'vehicule'])
            ->whereIn('statut', ['en_cours', 'reservee'])
            ->get();

        foreach ($locations as $l) {
            $qui = "{$l->numero} · {$l->client->nom_complet} · {$l->vehicule->marque} {$l->vehicule->modele}";
            $url = route('locations.show', $l, false);

            if ($l->statut === 'en_cours') {
                if ($l->date_retour_prevue->lt($maintenant)) {
                    $alertes["retard-{$l->id}"] = [
                        'niveau' => 'danger',
                        'icone' => 'warning',
                        'titre' => 'Location en retard',
                        'message' => "{$qui} : le retour était prévu le {$l->date_retour_prevue->format('d/m/Y à H:i')}.",
                        'url' => $url,
                    ];
                } elseif ($l->date_retour_prevue->lte($dans24h)) {
                    $alertes["retour-{$l->id}"] = [
                        'niveau' => 'warning',
                        'icone' => 'clock',
                        'titre' => 'Retour prévu bientôt',
                        'message' => "{$qui} : retour prévu le {$l->date_retour_prevue->format('d/m/Y à H:i')}.",
                        'url' => $url,
                    ];
                }
            } else {
                if ($l->date_depart->lt($maintenant)) {
                    $alertes["depart-depasse-{$l->id}"] = [
                        'niveau' => 'warning',
                        'icone' => 'key',
                        'titre' => 'Départ dépassé',
                        'message' => "{$qui} : le départ était prévu le {$l->date_depart->format('d/m/Y à H:i')}, la location n'a pas été démarrée.",
                        'url' => $url,
                    ];
                } elseif ($l->date_depart->lte($dans24h)) {
                    $alertes["depart-{$l->id}"] = [
                        'niveau' => 'info',
                        'icone' => 'key',
                        'titre' => 'Départ imminent',
                        'message' => "{$qui} : départ prévu le {$l->date_depart->format('d/m/Y à H:i')}.",
                        'url' => $url,
                    ];
                }
            }
        }

        foreach (Piece::enAlerte()->get() as $piece) {
            $epuise = $piece->quantite_stock <= 0;

            $alertes[($epuise ? 'epuise-' : 'stock-') . $piece->id] = [
                'niveau' => $epuise ? 'danger' : 'warning',
                'icone' => 'cube',
                'titre' => $epuise ? 'Stock épuisé' : 'Stock bas',
                'message' => "{$piece->designation} ({$piece->reference}) : {$piece->quantite_stock} en stock, seuil d'alerte {$piece->seuil_alerte}.",
                'url' => route('pieces.show', $piece, false),
            ];
        }

        // Sauvegarde : une alerte si la dernière a plus de 3 jours (ou s'il n'y en a aucune)
        $derniere = app(SauvegardeService::class)->derniere();

        if ($derniere === null || $derniere->lt(now()->subDays(3))) {
            $alertes['sauvegarde'] = [
                'niveau' => 'warning',
                'icone' => 'database',
                'titre' => 'Sauvegarde à faire',
                'message' => $derniere
                    ? "La dernière sauvegarde date du {$derniere->format('d/m/Y à H:i')}."
                    : "Aucune sauvegarde n'a encore été créée.",
                'url' => route('sauvegardes.index', [], false),
            ];
        }

        return $alertes;
    }

    private function creer(User $utilisateur, string $type, array $data): void
    {
        DatabaseNotification::create([
            'id' => (string) Str::uuid(),
            'type' => $type,
            'notifiable_type' => User::class,
            'notifiable_id' => $utilisateur->id,
            'data' => $data,
        ]);
    }

    private function utilisateurs(): Collection
    {
        return User::where('actif', true)->get();
    }
}