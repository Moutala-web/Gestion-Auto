@php
    $typesMouvement = [
        'entree' => ['Entrée', 'badge-green'],
        'sortie' => ['Sortie', 'badge-red'],
        'ajustement' => ['Ajustement', 'badge-blue'],
    ];
    $mouvementActif = old('_modal') === 'mouvement-stock';
    $enAlerte = $piece->estEnAlerte();
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="page-head">
            <div class="person">
                <span class="stat-icon {{ $piece->estDivers() ? 'stat-amber' : 'stat-teal' }}" style="width:3.4rem;height:3.4rem;border-radius:1rem">
                    <x-icon :name="$piece->estDivers() ? 'couch' : 'cube'" />
                </span>
                <div>
                    <h1 class="page-title">{{ $piece->designation }}</h1>
                    <p class="page-sub">
                        {{ $piece->reference }} · {{ $piece->libelle_famille }} &nbsp;
                        <span class="badge {{ $enAlerte ? 'badge-red' : 'badge-green' }}">{{ $enAlerte ? 'Stock bas' : 'Stock suffisant' }}</span>
                    </p>
                </div>
            </div>
            <div class="page-actions">
                <a href="{{ route('pieces.index', ['famille' => $piece->famille]) }}" class="btn btn-ghost"><x-icon name="arrow-left" /> Retour</a>
                <button type="button" x-data="" x-on:click.prevent="$dispatch('open-modal', 'modifier-piece')" class="btn btn-ghost">
                    <x-icon name="pencil" /> Modifier
                </button>
                <button type="button" x-data="" x-on:click.prevent="$dispatch('open-modal', 'mouvement-stock')" class="btn btn-ghost">
                    <x-icon name="trash" /> Sortie ou perte
                </button>
                @if ($piece->modele_id)
                    <a href="{{ route('achats.index', ['modele' => $piece->modele_id]) }}" class="btn btn-primary">
                        <x-icon name="inbox-in" /> Acheter
                    </a>
                @endif
            </div>
        </div>
    </x-slot>

    @include('partials.messages')

    <div class="stat-grid">
        <div class="card stat-card">
            <span class="stat-icon {{ $enAlerte ? 'stat-rose' : 'stat-teal' }}"><x-icon name="cube" /></span>
            <div><div class="stat-value">{{ $piece->quantite_stock }}</div><div class="stat-label">En stock</div></div>
        </div>
        <div class="card stat-card">
            <span class="stat-icon stat-amber"><x-icon name="warning" /></span>
            <div><div class="stat-value">{{ $piece->seuil_alerte }}</div><div class="stat-label">Seuil d'alerte</div></div>
        </div>
        <div class="card stat-card">
            <span class="stat-icon stat-indigo"><x-icon name="check-circle" /></span>
            <div>
                <div class="stat-value {{ $piece->marge_unitaire < 0 ? 'text-red-600' : '' }}">{{ number_format($piece->marge_unitaire, 0, ',', ' ') }}</div>
                <div class="stat-label">Marge unitaire (FCFA)</div>
            </div>
        </div>
    </div>

    <div class="grid-2">
        {{-- Informations --}}
        <div class="card card-pad">
            <h3 class="card-title">Informations</h3>
            <dl class="kv">
                <div><dt>Prix d'achat moyen</dt><dd>{{ number_format($piece->prix_achat, 0, ',', ' ') }} FCFA</dd></div>
                <div><dt>Prix de vente</dt><dd>{{ number_format($piece->prix_vente, 0, ',', ' ') }} FCFA</dd></div>
                <div><dt>Catégorie</dt><dd>{{ $piece->categorie ?: '—' }}</dd></div>
                <div><dt>Marque</dt><dd>{{ $piece->marque ?: '—' }}</dd></div>
                <div><dt>Emplacement</dt><dd>{{ $piece->emplacement ?: '—' }}</dd></div>
                <div><dt>Description</dt><dd>{{ $piece->description ?: '—' }}</dd></div>
            </dl>
            <p class="muted" style="font-size:.78rem;margin:1rem 0 0">
                Le stock augmente uniquement par un achat. Ici, on ne peut enregistrer que des sorties (usage interne, don) et des pertes (casse, écart d'inventaire).
            </p>
        </div>

        {{-- Historique des mouvements --}}
        <div class="card">
            <div class="card-pad" style="padding-bottom:.75rem">
                <h3 class="card-title" style="margin:0">Historique du stock</h3>
            </div>

            <div style="overflow-x:auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th class="num">Quantité</th>
                            <th class="num">Prix d'achat</th>
                            <th>Motif</th>
                            <th>Par</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($mouvements as $mouvement)
                            @php
                                // Une sortie diminue le stock ; un ajustement garde son signe ; une entrée l'augmente
                                $signe = $mouvement->type === 'sortie' ? -$mouvement->quantite : $mouvement->quantite;
                                [$typeLibelle, $typeClasse] = $typesMouvement[$mouvement->type] ?? [$mouvement->type, 'badge-gray'];
                            @endphp
                            <tr>
                                <td>{{ $mouvement->created_at->format('d/m/Y H:i') }}</td>
                                <td><span class="badge {{ $typeClasse }}">{{ $typeLibelle }}</span></td>
                                <td class="num" style="font-weight:800">
                                    <span class="{{ $signe < 0 ? 'text-red-600' : 'text-green-700' }}">{{ $signe > 0 ? '+' : '' }}{{ $signe }}</span>
                                </td>
                                <td class="num">{{ $mouvement->prix_achat !== null ? number_format($mouvement->prix_achat, 0, ',', ' ') : '—' }}</td>
                                <td>
                                    {{ $mouvement->motif ?: '—' }}
                                    @if ($mouvement->reference_operation)
                                        <div class="person-sub">Réf. {{ $mouvement->reference_operation }}</div>
                                    @endif
                                </td>
                                <td>{{ $mouvement->user?->name ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="empty">Aucun mouvement enregistré.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div style="padding:0 1rem 1rem">{{ $mouvements->links() }}</div>
        </div>
    </div>

    {{-- Fenêtre : sortie ou perte de stock --}}
    <x-modal name="mouvement-stock" :show="$mouvementActif && $errors->any()" focusable>
        <form method="POST" action="{{ route('pieces.mouvements.store', $piece) }}" class="p-6"
              x-data="{ type: '{{ $mouvementActif ? old('type', 'sortie') : 'sortie' }}' }">
            @csrf
            <input type="hidden" name="_modal" value="mouvement-stock">
            <h2 class="modal-title" style="margin-bottom:.25rem">Sortie ou perte de stock</h2>
            <p class="muted" style="font-size:.85rem;margin:0 0 1rem">Stock actuel : {{ $piece->quantite_stock }}. Pour ajouter du stock, enregistre un achat.</p>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                    <x-input-label value="Type *" />
                    <select name="type" x-model="type" class="mt-1 block w-full">
                        <option value="sortie">Sortie (usage interne, don...)</option>
                        <option value="ajustement">Perte ou écart d'inventaire (casse...)</option>
                    </select>
                    <x-input-error :messages="$mouvementActif ? $errors->get('type') : []" class="mt-2" />
                </div>

                <div>
                    <x-input-label value="Quantité *" />
                    <x-text-input name="quantite" type="number" step="1" class="mt-1 block w-full" :value="$mouvementActif ? old('quantite') : ''" required />
                    <p class="muted" style="font-size:.75rem;margin-top:.25rem" x-show="type === 'ajustement'">Saisis un nombre négatif (par exemple -2).</p>
                    <x-input-error :messages="$mouvementActif ? $errors->get('quantite') : []" class="mt-2" />
                </div>

                <div>
                    <x-input-label value="Référence (bon, note...)" />
                    <x-text-input name="reference_operation" type="text" class="mt-1 block w-full" :value="$mouvementActif ? old('reference_operation') : ''" />
                    <x-input-error :messages="$mouvementActif ? $errors->get('reference_operation') : []" class="mt-2" />
                </div>

                <div>
                    <x-input-label value="Motif" />
                    <x-text-input name="motif" type="text" class="mt-1 block w-full" :value="$mouvementActif ? old('motif') : ''" />
                    <p class="muted" style="font-size:.75rem;margin-top:.25rem" x-show="type === 'ajustement'">Obligatoire pour une perte ou un écart.</p>
                    <x-input-error :messages="$mouvementActif ? $errors->get('motif') : []" class="mt-2" />
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <x-secondary-button x-on:click="$dispatch('close')">Annuler</x-secondary-button>
                <x-primary-button>Enregistrer</x-primary-button>
            </div>
        </form>
    </x-modal>

    {{-- Fenêtre : modifier l'article --}}
    <x-modal name="modifier-piece" :show="old('_modal') === 'modifier-piece' && $errors->any()" focusable>
        <form method="POST" action="{{ route('pieces.update', $piece) }}" class="p-6">
            @method('PUT')
            <h2 class="modal-title">Informations de l'article</h2>

            @include('pieces._form', ['modal' => 'modifier-piece', 'piece' => $piece])

            <div class="mt-6 flex justify-end gap-3">
                <x-secondary-button x-on:click="$dispatch('close')">Annuler</x-secondary-button>
                <x-primary-button>Enregistrer</x-primary-button>
            </div>
        </form>
    </x-modal>
</x-app-layout>