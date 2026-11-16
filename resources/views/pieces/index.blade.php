@php
    $titre = match ($famille) {
        'divers' => 'Articles divers',
        'piece' => 'Pièces détachées',
        default => 'Pièces et articles divers',
    };

    // Les onglets conservent la recherche en cours, mais pas la catégorie (propre à chaque famille)
    $requete = request()->except(['page', 'famille', 'categorie']);
    $onglets = ['' => 'Toutes', 'piece' => 'Pièces', 'divers' => 'Articles divers'];
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="page-head">
            <div>
                <h1 class="page-title">{{ $titre }}</h1>
                <p class="page-sub">{{ $pieces->total() }} référence(s) en stock · le stock entre uniquement par un achat</p>
            </div>
            <div class="page-actions">
                <a href="{{ route('modeles.index', ['type' => $famille ?? 'piece']) }}" class="btn btn-ghost"><x-icon name="tags" /> Catalogue</a>
                <a href="{{ route('achats.index', ['acheter' => $famille ?? 'piece']) }}" class="btn btn-primary">
                    <x-icon name="inbox-in" /> Acheter
                </a>
            </div>
        </div>
    </x-slot>

    @include('partials.messages')

    <div class="toolbar">
        @foreach ($onglets as $cle => $libelle)
            <a href="{{ route('pieces.index', array_merge($requete, $cle === '' ? [] : ['famille' => $cle])) }}"
               class="btn {{ ($famille ?? '') === $cle ? 'btn-primary' : 'btn-ghost' }}">
                {{ $libelle }} <span style="opacity:.7">({{ $compteurs[$cle] }})</span>
            </a>
        @endforeach
    </div>

    <form method="GET" action="{{ route('pieces.index') }}" class="toolbar">
        @if ($famille)
            <input type="hidden" name="famille" value="{{ $famille }}">
        @endif

        <div class="search">
            <x-icon name="search" />
            <input type="text" name="q" value="{{ $recherche }}" placeholder="Référence, désignation, catégorie, marque">
        </div>
        <select name="categorie" style="min-width:12rem">
            <option value="">Toutes les catégories</option>
            @foreach ($categoriesFiltre as $categorieFiltre)
                <option value="{{ $categorieFiltre }}" @selected($categorie === $categorieFiltre)>{{ $categorieFiltre }}</option>
            @endforeach
        </select>
        <label style="display:flex;align-items:center;gap:.5rem;font-size:.88rem;font-weight:600">
            <input type="checkbox" name="alerte" value="1" @checked($alerte)>
            Stock bas uniquement
        </label>
        <button type="submit" class="btn btn-ghost">Filtrer</button>
        @if ($recherche !== '' || $alerte || $categorie)
            <a href="{{ route('pieces.index', $famille ? ['famille' => $famille] : []) }}" class="btn btn-ghost">Effacer</a>
        @endif
    </form>

    <div class="card" style="overflow-x:auto">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Article</th>
                    <th>Catégorie</th>
                    <th class="num">Stock</th>
                    <th class="num">Prix d'achat moyen</th>
                    <th class="num">Prix de vente</th>
                    <th class="num">Marge unitaire</th>
                    <th class="num">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($pieces as $piece)
                    <tr>
                        <td>
                            <div class="person">
                                <span class="stat-icon {{ $piece->estDivers() ? 'stat-amber' : 'stat-teal' }}" style="width:2.6rem;height:2.6rem;border-radius:.65rem">
                                    <x-icon :name="$piece->estDivers() ? 'couch' : 'cube'" />
                                </span>
                                <div>
                                    <a href="{{ route('pieces.show', $piece) }}" class="person-name link">{{ $piece->designation }}</a>
                                    <div class="person-sub">
                                        {{ $piece->reference }}
                                        @if ($piece->marque) · {{ $piece->marque }} @endif
                                        @if (! $famille) · {{ $piece->libelle_famille }} @endif
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td>{{ $piece->categorie ?: '—' }}</td>
                        <td class="num">
                            @if ($piece->estEnAlerte())
                                <span class="badge badge-red">{{ $piece->quantite_stock }} · stock bas</span>
                            @else
                                <span class="badge badge-green">{{ $piece->quantite_stock }}</span>
                            @endif
                        </td>
                        <td class="num">{{ number_format($piece->prix_achat, 0, ',', ' ') }}</td>
                        <td class="num">{{ number_format($piece->prix_vente, 0, ',', ' ') }}</td>
                        <td class="num" style="font-weight:700">
                            <span class="{{ $piece->marge_unitaire < 0 ? 'text-red-600' : 'text-green-700' }}">{{ number_format($piece->marge_unitaire, 0, ',', ' ') }}</span>
                        </td>
                        <td class="num">
                            <div class="row-actions">
                                <a href="{{ route('pieces.show', $piece) }}" class="icon-btn icon-btn-view" title="Voir le stock et l'historique"><x-icon name="eye" /></a>
                                <button type="button" x-data=""
                                        x-on:click.prevent="$dispatch('open-modal', 'modifier-piece-{{ $piece->id }}')"
                                        class="icon-btn icon-btn-edit" title="Modifier le prix de vente, le seuil, l'emplacement"><x-icon name="pencil" /></button>
                                <x-delete-modal :action="route('pieces.destroy', $piece)"
                                                titre="Supprimer cet article ?"
                                                :message="$piece->designation . ' (' . $piece->reference . ') sera supprimé. Un article qui a un achat ou un historique de stock ne peut pas être supprimé.'" />
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="empty">
                            Aucun article en stock. Définis-les dans le
                            <a href="{{ route('modeles.index', ['type' => $famille ?? 'piece']) }}" class="link">Catalogue</a>, puis
                            <a href="{{ route('achats.index', ['acheter' => $famille ?? 'piece']) }}" class="link">achète-les</a>.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <p class="muted" style="font-size:.78rem;margin-top:.6rem">
        Montants en FCFA. Le prix d'achat est la moyenne pondérée des achats. « stock bas » : le stock est au niveau d'alerte ou en dessous.
    </p>

    <div class="mt-4">{{ $pieces->links() }}</div>

    {{-- Fenêtres : modifier un article (une par ligne de la liste) --}}
    @foreach ($pieces as $piece)
        @php($nomModale = 'modifier-piece-' . $piece->id)

        <x-modal :name="$nomModale" :show="old('_modal') === $nomModale && $errors->any()" focusable>
            <form method="POST" action="{{ route('pieces.update', $piece) }}" class="p-6">
                @method('PUT')
                <h2 class="modal-title">Informations de l'article</h2>

                @include('pieces._form', ['modal' => $nomModale, 'piece' => $piece])

                <div class="mt-6 flex justify-end gap-3">
                    <x-secondary-button x-on:click="$dispatch('close')">Annuler</x-secondary-button>
                    <x-primary-button>Enregistrer</x-primary-button>
                </div>
            </form>
        </x-modal>
    @endforeach
</x-app-layout>