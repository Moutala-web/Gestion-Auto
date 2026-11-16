@php
    $onglets = ['' => 'Tous'] + \App\Models\Modele::TYPES;
    $requete = request()->except(['page', 'type', 'marque']);
    $fmt = fn ($n) => number_format((float) $n, 0, ',', ' ');
    $icones = ['voiture' => 'truck', 'moto' => 'bolt', 'piece' => 'cube', 'divers' => 'couch'];
    $couleurs = ['voiture' => 'stat-amber', 'moto' => 'stat-indigo', 'piece' => 'stat-teal', 'divers' => 'stat-sky'];
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="page-head">
            <div>
                <h1 class="page-title">Catalogue</h1>
                <p class="page-sub">{{ $modeles->total() }} élément(s) · voitures, motos, pièces et articles : tout ce qui peut être acheté se définit ici</p>
            </div>
            <div class="page-actions">
                <button type="button" x-data="" x-on:click.prevent="$dispatch('open-modal', 'creer-modele')" class="btn btn-primary">
                    <x-icon name="plus" /> Nouveau
                </button>
            </div>
        </div>
    </x-slot>

    @include('partials.messages')

    {{-- Suggestions de catégories pour tous les formulaires de la page --}}
    <datalist id="liste-categories-modeles">
        @foreach ($suggestions as $suggestion)
            <option value="{{ $suggestion }}"></option>
        @endforeach
    </datalist>

    <div class="toolbar">
        @foreach ($onglets as $cle => $libelle)
            <a href="{{ route('modeles.index', array_merge($requete, $cle === '' ? [] : ['type' => $cle])) }}"
               class="btn {{ ($type ?? '') === $cle ? 'btn-primary' : 'btn-ghost' }}">
                {{ $libelle }} <span style="opacity:.7">({{ $compteurs[$cle] }})</span>
            </a>
        @endforeach
    </div>

    <form method="GET" action="{{ route('modeles.index') }}" class="toolbar">
        @if ($type)
            <input type="hidden" name="type" value="{{ $type }}">
        @endif

        <div class="search">
            <x-icon name="search" />
            <input type="text" name="q" value="{{ $recherche }}" placeholder="Marque, modèle, désignation ou catégorie">
        </div>
        <select name="marque" style="min-width:12rem">
            <option value="">Toutes les marques</option>
            @foreach ($marques as $m)
                <option value="{{ $m }}" @selected($marque === $m)>{{ $m }}</option>
            @endforeach
        </select>
        <button type="submit" class="btn btn-ghost">Filtrer</button>
        @if ($recherche !== '' || $marque)
            <a href="{{ route('modeles.index', $type ? ['type' => $type] : []) }}" class="btn btn-ghost">Effacer</a>
        @endif
    </form>

    <div class="card" style="overflow-x:auto">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Élément</th>
                    <th>Caractéristiques</th>
                    <th class="num">Prix de vente</th>
                    <th class="num">En stock</th>
                    <th class="num">Vendus</th>
                    <th class="num">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($modeles as $modele)
                    @php
                        $disponibles = match ($modele->type) {
                            'moto' => $modele->motos_disponibles_count,
                            'voiture' => $modele->vehicules_disponibles_count,
                            default => (int) $modele->pieces_sum_quantite_stock,
                        };
                        $vendus = match ($modele->type) {
                            'moto' => $modele->motos_vendues_count,
                            'voiture' => $modele->vehicules_vendus_count,
                            default => null,
                        };
                    @endphp
                    <tr>
                        <td>
                            <div class="person">
                                <span class="stat-icon {{ $couleurs[$modele->type] }}" style="width:2.6rem;height:2.6rem;border-radius:.65rem">
                                    <x-icon :name="$icones[$modele->type]" />
                                </span>
                                <div>
                                    <div class="person-name">{{ $modele->nom_complet }}</div>
                                    <div class="person-sub">{{ \App\Models\Modele::TYPES[$modele->type] ?? $modele->type }}</div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div>{{ $modele->categorie ?: '—' }}</div>
                            <div class="person-sub">
                                @if ($modele->type === 'moto')
                                    {{ $modele->cylindree ? $modele->cylindree . ' cm³' : '' }}
                                @elseif ($modele->type === 'voiture')
                                    {{ implode(' · ', array_filter([
                                        \App\Models\Modele::CARBURANTS[$modele->carburant] ?? null,
                                        \App\Models\Modele::BOITES[$modele->boite] ?? null,
                                    ])) }}
                                @else
                                    Seuil d'alerte : {{ $modele->seuil_alerte }}
                                @endif
                            </div>
                        </td>
                        <td class="num">{{ $modele->prix_vente_indicatif !== null ? $fmt($modele->prix_vente_indicatif) : '—' }}</td>
                        <td class="num">
                            <span class="badge {{ $disponibles > 0 ? 'badge-green' : 'badge-gray' }}">{{ $disponibles }}</span>
                        </td>
                        <td class="num">{{ $vendus ?? '—' }}</td>
                        <td class="num">
                            <div class="row-actions">
                                <a href="{{ route('achats.index', ['modele' => $modele->id]) }}" class="icon-btn icon-btn-view" title="Acheter"><x-icon name="inbox-in" /></a>
                                <button type="button" x-data=""
                                        x-on:click.prevent="$dispatch('open-modal', 'modifier-modele-{{ $modele->id }}')"
                                        class="icon-btn icon-btn-edit" title="Modifier"><x-icon name="pencil" /></button>
                                <x-delete-modal :action="route('modeles.destroy', $modele)"
                                                titre="Supprimer du catalogue ?"
                                                :message="$modele->nom_complet . ' sera retiré du catalogue. Un élément utilisé par des fiches ou du stock ne peut pas être supprimé.'" />
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty">Le catalogue est vide pour cette sélection.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <p class="muted" style="font-size:.78rem;margin-top:.6rem">
        « En stock » compte les fiches disponibles (voitures, motos) ou la quantité (pièces, articles divers).
        On ne crée jamais une fiche à la main : on achète un modèle du catalogue (icône de caisse), et les fiches se créent toutes seules.
        Renommer un élément met à jour toutes ses fiches.
    </p>

    <div class="mt-4">{{ $modeles->links() }}</div>

    {{-- Fenêtre : nouvel élément du catalogue --}}
    <x-modal name="creer-modele" :show="old('_modal') === 'creer-modele' && $errors->any()" focusable>
        <form method="POST" action="{{ route('modeles.store') }}" class="p-6">
            <h2 class="modal-title">Nouvel élément du catalogue</h2>

            @include('modeles._form', ['modal' => 'creer-modele', 'modele' => null, 'typeParDefaut' => $type ?? 'voiture'])

            <div class="mt-6 flex justify-end gap-3">
                <x-secondary-button x-on:click="$dispatch('close')">Annuler</x-secondary-button>
                <x-primary-button>Enregistrer</x-primary-button>
            </div>
        </form>
    </x-modal>

    {{-- Fenêtres : modifier un élément (une par ligne de la liste) --}}
    @foreach ($modeles as $modele)
        @php($nomModale = 'modifier-modele-' . $modele->id)

        <x-modal :name="$nomModale" :show="old('_modal') === $nomModale && $errors->any()" focusable>
            <form method="POST" action="{{ route('modeles.update', $modele) }}" class="p-6">
                @method('PUT')
                <h2 class="modal-title">Modifier</h2>

                @include('modeles._form', ['modal' => $nomModale, 'modele' => $modele])

                <div class="mt-6 flex justify-end gap-3">
                    <x-secondary-button x-on:click="$dispatch('close')">Annuler</x-secondary-button>
                    <x-primary-button>Mettre à jour</x-primary-button>
                </div>
            </form>
        </x-modal>
    @endforeach
</x-app-layout>