@php
    $ancienne = $derniere === null || $derniere->lt(now()->subDays(3));

    $taille = function ($octets) {
        if ($octets >= 1048576) {
            return number_format($octets / 1048576, 1, ',', ' ') . ' Mo';
        }

        return number_format(max($octets / 1024, 0.1), 1, ',', ' ') . ' Ko';
    };
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="page-head">
            <div>
                <h1 class="page-title">Sauvegardes</h1>
                <p class="page-sub">
                    @if ($derniere)
                        Dernière sauvegarde : {{ $derniere->format('d/m/Y à H:i') }} ({{ $derniere->diffForHumans() }})
                    @else
                        Aucune sauvegarde pour l'instant
                    @endif
                </p>
            </div>
            <div class="page-actions">
                <form method="POST" action="{{ route('sauvegardes.store') }}">
                    @csrf
                    <button type="submit" class="btn btn-primary"><x-icon name="plus" /> Créer une sauvegarde</button>
                </form>
            </div>
        </div>
    </x-slot>

    @include('partials.messages')

    @if ($ancienne)
        <div class="flash flash-error">
            <x-icon name="warning" />
            <span>{{ $derniere ? 'La dernière sauvegarde a plus de 3 jours.' : 'Aucune sauvegarde n\'a été créée.' }} Crée-en une maintenant.</span>
        </div>
    @endif

    <div class="grid-2">
        <div class="stack">
            <div class="card card-pad">
                <h3 class="card-title">Ce que contient une sauvegarde</h3>
                <ul style="margin:0;padding-left:1.1rem;font-size:.88rem;line-height:1.7">
                    <li>Toute la base de données : clients, véhicules, ventes, locations, caisse...</li>
                    <li>Le logo de l'entreprise</li>
                </ul>
                <p class="muted" style="font-size:.8rem;margin:.9rem 0 0">
                    Les {{ \App\Services\SauvegardeService::CONSERVER }} dernières sauvegardes sont conservées sur le serveur.
                </p>
            </div>

            <div class="card card-pad">
                <h3 class="card-title">Restaurer une sauvegarde</h3>
                <ol style="margin:0;padding-left:1.1rem;font-size:.85rem;line-height:1.7">
                    <li>Télécharge le fichier ZIP et décompresse-le.</li>
                    <li>Dans phpMyAdmin, ouvre la base de données, onglet <strong>Importer</strong>, puis choisis <code>base-de-donnees.sql</code>.</li>
                    <li>Copie le dossier <code>uploads</code> dans le dossier <code>public</code> du site.</li>
                </ol>
                <p class="muted" style="font-size:.78rem;margin:.9rem 0 0">
                    L'import remplace les tables existantes : fais-le d'abord sur une base de test.
                </p>
            </div>

            <div class="card card-pad">
                <h3 class="card-title">Bon réflexe</h3>
                <p class="muted" style="font-size:.85rem;margin:0">
                    Télécharge régulièrement une sauvegarde et garde-la <strong>ailleurs que sur le serveur</strong>
                    (clé USB, ordinateur, Google Drive). Si le serveur tombe en panne, les sauvegardes qu'il contient tombent avec lui.
                </p>
            </div>
        </div>

        <div class="card">
            <div class="card-pad" style="padding-bottom:.75rem">
                <h3 class="card-title" style="margin:0">Sauvegardes disponibles</h3>
            </div>

            <div style="overflow-x:auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Fichier</th>
                            <th>Date</th>
                            <th class="num">Taille</th>
                            <th class="num">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($sauvegardes as $sauvegarde)
                            <tr>
                                <td class="person-name">{{ $sauvegarde['nom'] }}</td>
                                <td>
                                    {{ $sauvegarde['date']->format('d/m/Y H:i') }}
                                    <div class="person-sub">{{ $sauvegarde['date']->diffForHumans() }}</div>
                                </td>
                                <td class="num">{{ $taille($sauvegarde['taille']) }}</td>
                                <td class="num">
                                    <div class="row-actions">
                                        <a href="{{ route('sauvegardes.telecharger', $sauvegarde['nom']) }}" class="icon-btn icon-btn-view" title="Télécharger">
                                            <x-icon name="download" />
                                        </a>
                                        <x-delete-modal :action="route('sauvegardes.destroy', $sauvegarde['nom'])"
                                                        titre="Supprimer cette sauvegarde ?"
                                                        :message="$sauvegarde['nom'] . ' sera supprimée définitivement.'" />
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="empty">Aucune sauvegarde.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>