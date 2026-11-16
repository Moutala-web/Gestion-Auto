@php
    $types = ['client' => ['Client', 'badge-green'], 'prospect' => ['Prospect', 'badge-yellow']];
    $statutsVente = ['validee' => ['Validée', 'badge-green'], 'annulee' => ['Annulée', 'badge-red']];
    $statutsLocation = [
        'reservee' => ['Réservée', 'badge-yellow'],
        'en_cours' => ['En cours', 'badge-blue'],
        'terminee' => ['Terminée', 'badge-green'],
        'annulee' => ['Annulée', 'badge-red'],
    ];
    [$typeLibelle, $typeClasse] = $types[$client->type] ?? [$client->type, 'badge-gray'];
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="page-head">
            <div class="person">
                <span class="avatar avatar-soft avatar-lg">{{ mb_strtoupper(mb_substr($client->nom, 0, 1)) }}</span>
                <div>
                    <h1 class="page-title">{{ $client->nom_complet }}</h1>
                    <p class="page-sub"><span class="badge {{ $typeClasse }}">{{ $typeLibelle }}</span></p>
                </div>
            </div>
            <div class="page-actions">
                <a href="{{ route('clients.index') }}" class="btn btn-ghost"><x-icon name="arrow-left" /> Retour</a>
                <button type="button" x-data="" x-on:click.prevent="$dispatch('open-modal', 'modifier-client')" class="btn btn-primary">
                    <x-icon name="pencil" /> Modifier
                </button>
            </div>
        </div>
    </x-slot>

    @include('partials.messages')

    <div class="grid-2">
        <div class="card card-pad">
            <h3 class="card-title">Coordonnées</h3>
            <dl class="kv">
                <div><dt>Téléphone</dt><dd>{{ $client->telephone }}</dd></div>
                <div><dt>Email</dt><dd>{{ $client->email ?: '—' }}</dd></div>
                <div><dt>Adresse</dt><dd>{{ $client->adresse ?: '—' }}</dd></div>
                <div><dt>Notes</dt><dd>{{ $client->notes ?: '—' }}</dd></div>
            </dl>
        </div>

        <div class="stack">
            <div class="card card-pad">
                <h3 class="card-title">Ventes</h3>
                @forelse ($client->ventes as $vente)
                    @php([$venteLibelle, $venteClasse] = $statutsVente[$vente->statut] ?? [$vente->statut, 'badge-gray'])
                    <div class="list-row">
                        <div>
                            <div class="list-main">{{ $vente->numero }}</div>
                            <div class="list-sub">{{ $vente->date_vente->format('d/m/Y') }}</div>
                        </div>
                        <div style="text-align:right">
                            <div class="list-main">{{ number_format($vente->total, 0, ',', ' ') }} FCFA</div>
                            <span class="badge {{ $venteClasse }}">{{ $venteLibelle }}</span>
                        </div>
                    </div>
                @empty
                    <p class="muted" style="font-size:.9rem">Aucune vente.</p>
                @endforelse
            </div>

            <div class="card card-pad">
                <h3 class="card-title">Locations</h3>
                @forelse ($client->locations as $location)
                    @php([$locLibelle, $locClasse] = $statutsLocation[$location->statut] ?? [$location->statut, 'badge-gray'])
                    <div class="list-row">
                        <div>
                            <div class="list-main">{{ $location->numero }} · {{ $location->vehicule->marque }} {{ $location->vehicule->modele }}</div>
                            <div class="list-sub">Départ le {{ $location->date_depart->format('d/m/Y') }}</div>
                        </div>
                        <div style="text-align:right">
                            <div class="list-main">{{ number_format($location->tarif, 0, ',', ' ') }} FCFA</div>
                            <span class="badge {{ $locClasse }}">{{ $locLibelle }}</span>
                        </div>
                    </div>
                @empty
                    <p class="muted" style="font-size:.9rem">Aucune location.</p>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Fenêtre : modifier le client --}}
    <x-modal name="modifier-client" :show="old('_modal') === 'modifier-client' && $errors->any()" focusable>
        <form method="POST" action="{{ route('clients.update', $client) }}" class="p-6">
            @method('PUT')
            <h2 class="modal-title">Modifier le client</h2>

            @include('clients._form', ['modal' => 'modifier-client', 'client' => $client])

            <div class="mt-6 flex justify-end gap-3">
                <x-secondary-button x-on:click="$dispatch('close')">Annuler</x-secondary-button>
                <x-primary-button>Mettre à jour</x-primary-button>
            </div>
        </form>
    </x-modal>
</x-app-layout>