@php
    $statuts = [
        'disponible' => 'Disponible',
        'reserve' => 'Réservé',
        'en_location' => 'En location',
        'vendu' => 'Vendu',
        'maintenance' => 'Maintenance',
    ];
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="page-head">
            <div>
                <h1 class="page-title">Véhicules</h1>
                <p class="page-sub">{{ $vehicules->total() }} véhicule(s) · le stock entre uniquement par un achat</p>
            </div>
            <div class="page-actions">
                <a href="{{ route('modeles.index', ['type' => 'voiture']) }}" class="btn btn-ghost"><x-icon name="tags" /> Catalogue</a>
                <a href="{{ route('achats.index', ['acheter' => 'vehicule']) }}" class="btn btn-primary">
                    <x-icon name="inbox-in" /> Acheter des véhicules
                </a>
            </div>
        </div>
    </x-slot>

    @include('partials.messages')

    <form method="GET" action="{{ route('vehicules.index') }}" class="toolbar">
        <div class="search">
            <x-icon name="search" />
            <input type="text" name="q" value="{{ $recherche }}" placeholder="Référence, marque, modèle, immatriculation, châssis">
        </div>
        <select name="statut" style="min-width:11rem">
            <option value="">Tous les statuts</option>
            @foreach ($statuts as $valeur => $libelle)
                <option value="{{ $valeur }}" @selected($statut === $valeur)>{{ $libelle }}</option>
            @endforeach
        </select>
        <button type="submit" class="btn btn-ghost">Filtrer</button>
        @if ($recherche !== '' || $statut)
            <a href="{{ route('vehicules.index') }}" class="btn btn-ghost">Effacer</a>
        @endif
    </form>

    <div class="card" style="overflow-x:auto">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Véhicule</th>
                    <th>Statut</th>
                    <th class="num">Coût total</th>
                    <th class="num">Prix de vente</th>
                    <th class="num">Marge</th>
                    <th class="num">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($vehicules as $vehicule)
                    @php($marge = $vehicule->marge)
                    <tr>
                        <td>
                            <div class="person">
                                <span class="stat-icon stat-amber" style="width:2.6rem;height:2.6rem;border-radius:.65rem"><x-icon name="truck" /></span>
                                <div>
                                    <a href="{{ route('vehicules.show', $vehicule) }}" class="person-name link">
                                        {{ $vehicule->marque }} {{ $vehicule->modele }}
                                    </a>
                                    <div class="person-sub">
                                        {{ $vehicule->reference }}
                                        @if ($vehicule->annee) · {{ $vehicule->annee }} @endif
                                        @if ($vehicule->immatriculation) · {{ $vehicule->immatriculation }} @endif
                                        @if (! $vehicule->immatriculation && ! $vehicule->numero_chassis) · <span style="color:#a16207">à compléter</span> @endif
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td>@include('vehicules._statut', ['statut' => $vehicule->statut])</td>
                        <td class="num">{{ number_format($vehicule->cout_total, 0, ',', ' ') }}</td>
                        <td class="num">{{ $vehicule->prix_vente !== null ? number_format($vehicule->prix_vente, 0, ',', ' ') : '—' }}</td>
                        <td class="num" style="font-weight:700">
                            @if ($marge === null)
                                —
                            @else
                                <span class="{{ $marge < 0 ? 'text-red-600' : 'text-green-700' }}">{{ number_format($marge, 0, ',', ' ') }}</span>
                            @endif
                        </td>
                        <td class="num">
                            <div class="row-actions">
                                <a href="{{ route('vehicules.show', $vehicule) }}" class="icon-btn icon-btn-view" title="Voir la fiche et les frais"><x-icon name="eye" /></a>
                                <button type="button" x-data=""
                                        x-on:click.prevent="$dispatch('open-modal', 'modifier-vehicule-{{ $vehicule->id }}')"
                                        class="icon-btn icon-btn-edit" title="Compléter ou modifier les informations"><x-icon name="pencil" /></button>
                                <x-delete-modal :action="route('vehicules.destroy', $vehicule)"
                                                titre="Supprimer ce véhicule ?"
                                                :message="$vehicule->marque . ' ' . $vehicule->modele . ' (' . $vehicule->reference . ') sera supprimé. Un véhicule qui a un achat, des frais, une location ou une vente ne peut pas être supprimé.'" />
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="empty">
                            Aucun véhicule en stock. Définis les modèles dans le Catalogue, puis
                            <a href="{{ route('achats.index', ['acheter' => 'vehicule']) }}" class="link">achète-les</a> : les fiches se créent toutes seules.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <p class="muted" style="font-size:.78rem;margin-top:.6rem">
        Montants en FCFA. Coût total = prix d'achat + frais. « à compléter » : l'immatriculation et le numéro de châssis ne sont pas encore renseignés.
    </p>

    <div class="mt-4">{{ $vehicules->links() }}</div>

    {{-- Fenêtres : compléter ou modifier les informations d'un véhicule (une par ligne de la liste) --}}
    @foreach ($vehicules as $vehicule)
        @php($nomModale = 'modifier-vehicule-' . $vehicule->id)

        <x-modal :name="$nomModale" :show="old('_modal') === $nomModale && $errors->any()" focusable>
            <form method="POST" action="{{ route('vehicules.update', $vehicule) }}" class="p-6">
                @method('PUT')
                <h2 class="modal-title">Informations du véhicule</h2>

                @include('vehicules._form', ['modal' => $nomModale, 'vehicule' => $vehicule])

                <div class="mt-6 flex justify-end gap-3">
                    <x-secondary-button x-on:click="$dispatch('close')">Annuler</x-secondary-button>
                    <x-primary-button>Enregistrer</x-primary-button>
                </div>
            </form>
        </x-modal>
    @endforeach
</x-app-layout>