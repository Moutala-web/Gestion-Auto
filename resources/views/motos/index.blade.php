@php
    $statuts = ['disponible' => 'Disponible', 'reserve' => 'Réservée', 'vendu' => 'Vendue'];
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="page-head">
            <div>
                <h1 class="page-title">Motos</h1>
                <p class="page-sub">{{ $motos->total() }} moto(s) · le stock entre uniquement par un achat</p>
            </div>
            <div class="page-actions">
                <a href="{{ route('modeles.index', ['type' => 'moto']) }}" class="btn btn-ghost"><x-icon name="tags" /> Catalogue</a>
                <a href="{{ route('achats.index', ['acheter' => 'moto']) }}" class="btn btn-primary">
                    <x-icon name="inbox-in" /> Acheter des motos
                </a>
            </div>
        </div>
    </x-slot>

    @include('partials.messages')

    <form method="GET" action="{{ route('motos.index') }}" class="toolbar">
        <div class="search">
            <x-icon name="search" />
            <input type="text" name="q" value="{{ $recherche }}" placeholder="Référence, marque, modèle, châssis">
        </div>
        <select name="statut" style="min-width:11rem">
            <option value="">Tous les statuts</option>
            @foreach ($statuts as $valeur => $libelle)
                <option value="{{ $valeur }}" @selected($statut === $valeur)>{{ $libelle }}</option>
            @endforeach
        </select>
        <button type="submit" class="btn btn-ghost">Filtrer</button>
        @if ($recherche !== '' || $statut)
            <a href="{{ route('motos.index') }}" class="btn btn-ghost">Effacer</a>
        @endif
    </form>

    <div class="card" style="overflow-x:auto">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Moto</th>
                    <th>Statut</th>
                    <th class="num">Prix d'achat</th>
                    <th class="num">Prix de vente</th>
                    <th class="num">Marge</th>
                    <th class="num">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($motos as $moto)
                    @php($marge = $moto->marge)
                    <tr>
                        <td>
                            <div class="person">
                                <span class="stat-icon stat-indigo" style="width:2.6rem;height:2.6rem;border-radius:.65rem"><x-icon name="bolt" /></span>
                                <div>
                                    <div class="person-name">{{ $moto->marque }} {{ $moto->modele }}</div>
                                    <div class="person-sub">
                                        {{ $moto->reference }}
                                        @if ($moto->annee) · {{ $moto->annee }} @endif
                                        @if ($moto->couleur) · {{ $moto->couleur }} @endif
                                        @if ($moto->numero_chassis) · VIN {{ $moto->numero_chassis }} @else · <span style="color:#a16207">à compléter</span> @endif
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td>@include('vehicules._statut', ['statut' => $moto->statut])</td>
                        <td class="num">{{ number_format($moto->prix_achat, 0, ',', ' ') }}</td>
                        <td class="num">{{ $moto->prix_vente !== null ? number_format($moto->prix_vente, 0, ',', ' ') : '—' }}</td>
                        <td class="num" style="font-weight:700">
                            @if ($marge === null)
                                —
                            @else
                                <span class="{{ $marge < 0 ? 'text-red-600' : 'text-green-700' }}">{{ number_format($marge, 0, ',', ' ') }}</span>
                            @endif
                        </td>
                        <td class="num">
                            <div class="row-actions">
                                <button type="button" x-data=""
                                        x-on:click.prevent="$dispatch('open-modal', 'modifier-moto-{{ $moto->id }}')"
                                        class="icon-btn icon-btn-edit" title="Compléter ou modifier les informations"><x-icon name="pencil" /></button>
                                <x-delete-modal :action="route('motos.destroy', $moto)"
                                                titre="Supprimer cette moto ?"
                                                :message="$moto->marque . ' ' . $moto->modele . ' (' . $moto->reference . ') sera supprimée. Une moto qui a un achat ou une vente ne peut pas être supprimée.'" />
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="empty">
                            Aucune moto en stock. Définis les modèles dans le Catalogue, puis
                            <a href="{{ route('achats.index', ['acheter' => 'moto']) }}" class="link">achète-les</a> : les fiches se créent toutes seules.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <p class="muted" style="font-size:.78rem;margin-top:.6rem">Montants en FCFA. Marge prévisionnelle = prix de vente − prix d'achat.</p>

    <div class="mt-4">{{ $motos->links() }}</div>

    {{-- Fenêtres : compléter ou modifier les informations d'une moto (une par ligne de la liste) --}}
    @foreach ($motos as $moto)
        @php($nomModale = 'modifier-moto-' . $moto->id)

        <x-modal :name="$nomModale" :show="old('_modal') === $nomModale && $errors->any()" focusable>
            <form method="POST" action="{{ route('motos.update', $moto) }}" class="p-6">
                @method('PUT')
                <h2 class="modal-title">Informations de la moto</h2>

                @include('motos._form', ['modal' => $nomModale, 'moto' => $moto])

                <div class="mt-6 flex justify-end gap-3">
                    <x-secondary-button x-on:click="$dispatch('close')">Annuler</x-secondary-button>
                    <x-primary-button>Enregistrer</x-primary-button>
                </div>
            </form>
        </x-modal>
    @endforeach
</x-app-layout>