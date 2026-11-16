@php
    $types = ['client' => ['Client', 'badge-green'], 'prospect' => ['Prospect', 'badge-yellow']];
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="page-head">
            <div>
                <h1 class="page-title">Clients</h1>
                <p class="page-sub">{{ $clients->total() }} contact(s) enregistré(s)</p>
            </div>
            <div class="page-actions">
                <button type="button" x-data="" x-on:click.prevent="$dispatch('open-modal', 'creer-client')" class="btn btn-primary">
                    <x-icon name="plus" /> Nouveau client
                </button>
            </div>
        </div>
    </x-slot>

    @include('partials.messages')

    <form method="GET" action="{{ route('clients.index') }}" class="toolbar">
        <div class="search">
            <x-icon name="search" />
            <input type="text" name="q" value="{{ $recherche }}" placeholder="Rechercher un nom ou un téléphone">
        </div>
        <button type="submit" class="btn btn-ghost">Rechercher</button>
        @if ($recherche !== '')
            <a href="{{ route('clients.index') }}" class="btn btn-ghost">Effacer</a>
        @endif
    </form>

    <div class="card" style="overflow-x:auto">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Client</th>
                    <th>Téléphone</th>
                    <th>Type</th>
                    <th class="num">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($clients as $client)
                    @php([$typeLibelle, $typeClasse] = $types[$client->type] ?? [$client->type, 'badge-gray'])
                    <tr>
                        <td>
                            <div class="person">
                                <span class="avatar avatar-soft">{{ mb_strtoupper(mb_substr($client->nom, 0, 1)) }}</span>
                                <div>
                                    <a href="{{ route('clients.show', $client) }}" class="person-name link">{{ $client->nom_complet }}</a>
                                    <div class="person-sub">{{ $client->email ?: "Pas d'email" }}</div>
                                </div>
                            </div>
                        </td>
                        <td>{{ $client->telephone }}</td>
                        <td><span class="badge {{ $typeClasse }}">{{ $typeLibelle }}</span></td>
                        <td class="num">
                            <div class="row-actions">
                                <a href="{{ route('clients.show', $client) }}" class="icon-btn icon-btn-view" title="Voir la fiche"><x-icon name="eye" /></a>
                                <button type="button" x-data=""
                                        x-on:click.prevent="$dispatch('open-modal', 'modifier-client-{{ $client->id }}')"
                                        class="icon-btn icon-btn-edit" title="Modifier"><x-icon name="pencil" /></button>
                                <x-delete-modal :action="route('clients.destroy', $client)"
                                                titre="Supprimer ce client ?"
                                                :message="'Le client ' . $client->nom_complet . ' sera supprimé définitivement.'" />
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="empty">Aucun client trouvé.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $clients->links() }}</div>

    {{-- Fenêtre : nouveau client --}}
    <x-modal name="creer-client" :show="old('_modal') === 'creer-client' && $errors->any()" focusable>
        <form method="POST" action="{{ route('clients.store') }}" class="p-6">
            <h2 class="modal-title">Nouveau client</h2>

            @include('clients._form', ['modal' => 'creer-client', 'client' => null])

            <div class="mt-6 flex justify-end gap-3">
                <x-secondary-button x-on:click="$dispatch('close')">Annuler</x-secondary-button>
                <x-primary-button>Enregistrer</x-primary-button>
            </div>
        </form>
    </x-modal>

    {{-- Fenêtres : modifier un client (une par ligne de la liste) --}}
    @foreach ($clients as $client)
        @php($nomModale = 'modifier-client-' . $client->id)

        <x-modal :name="$nomModale" :show="old('_modal') === $nomModale && $errors->any()" focusable>
            <form method="POST" action="{{ route('clients.update', $client) }}" class="p-6">
                @method('PUT')
                <h2 class="modal-title">Modifier le client</h2>

                @include('clients._form', ['modal' => $nomModale, 'client' => $client])

                <div class="mt-6 flex justify-end gap-3">
                    <x-secondary-button x-on:click="$dispatch('close')">Annuler</x-secondary-button>
                    <x-primary-button>Mettre à jour</x-primary-button>
                </div>
            </form>
        </x-modal>
    @endforeach
</x-app-layout>