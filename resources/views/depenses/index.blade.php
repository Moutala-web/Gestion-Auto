@php
    $modes = ['especes' => 'Espèces', 'orange_money' => 'Orange Money'];
    $libelles = \App\Models\MouvementCaisse::LIBELLES;
    $fmt = fn ($n) => number_format((float) $n, 0, ',', ' ');
    $actif = old('_modal') === 'creer-depense';

    // Les onglets conservent les filtres en cours (dates, recherche...)
    $requete = request()->except(['page', 'nature']);
    $onglets = ['' => 'Toutes', 'achat' => 'Achats', 'frais_vehicule' => 'Frais de véhicules', 'depense' => 'Fonctionnement'];

    $classesNature = [
        'achat' => 'badge-blue',
        'frais_vehicule' => 'badge-yellow',
        'depense' => 'badge-gray',
        'annulation_achat' => 'badge-green',
        'annulation_frais' => 'badge-green',
        'annulation_depense' => 'badge-green',
    ];
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="page-head">
            <div>
                <h1 class="page-title">Dépenses</h1>
                <p class="page-sub">
                    Achats, frais de véhicules et dépenses de fonctionnement · total net
                    <strong>{{ $fmt($totaux['total']) }} FCFA</strong> sur la période
                </p>
            </div>
            <div class="page-actions">
                <a href="{{ route('achats.index') }}" class="btn btn-ghost"><x-icon name="inbox-in" /> Enregistrer un achat</a>
                <button type="button" x-data="" x-on:click.prevent="$dispatch('open-modal', 'creer-depense')" class="btn btn-primary">
                    <x-icon name="plus" /> Nouvelle dépense
                </button>
            </div>
        </div>
    </x-slot>

    @include('partials.messages')

    {{-- Chiffres de la période --}}
    <div class="stat-grid">
        <div class="card stat-card">
            <span class="stat-icon stat-rose"><x-icon name="receipt" /></span>
            <div>
                <div class="stat-value">{{ $fmt($totaux['total']) }}</div>
                <div class="stat-label">Total des dépenses (FCFA)</div>
            </div>
        </div>
        <div class="card stat-card">
            <span class="stat-icon stat-indigo"><x-icon name="inbox-in" /></span>
            <div>
                <div class="stat-value">{{ $fmt($totaux['achat']) }}</div>
                <div class="stat-label">Achats (véhicules, motos, pièces)</div>
            </div>
        </div>
        <div class="card stat-card">
            <span class="stat-icon stat-amber"><x-icon name="truck" /></span>
            <div>
                <div class="stat-value">{{ $fmt($totaux['frais_vehicule']) }}</div>
                <div class="stat-label">Frais de véhicules</div>
            </div>
        </div>
        <div class="card stat-card">
            <span class="stat-icon stat-sky"><x-icon name="cog" /></span>
            <div>
                <div class="stat-value">{{ $fmt($totaux['depense']) }}</div>
                <div class="stat-label">Fonctionnement</div>
            </div>
        </div>
    </div>

    {{-- Onglets par nature --}}
    <div class="toolbar">
        @foreach ($onglets as $cle => $libelle)
            <a href="{{ route('depenses.index', array_merge($requete, $cle === '' ? [] : ['nature' => $cle])) }}"
               class="btn {{ ($nature ?? '') === $cle ? 'btn-primary' : 'btn-ghost' }}">{{ $libelle }}</a>
        @endforeach
    </div>

    <form method="GET" action="{{ route('depenses.index') }}" class="toolbar">
        @if ($nature)
            <input type="hidden" name="nature" value="{{ $nature }}">
        @endif

        <div class="search">
            <x-icon name="search" />
            <input type="text" name="q" value="{{ $recherche }}" placeholder="Rechercher dans les descriptions">
        </div>
        <select name="categorie" style="min-width:12rem" title="Catégorie des dépenses de fonctionnement">
            <option value="">Toutes les catégories</option>
            @foreach ($categories as $cle => $libelle)
                <option value="{{ $cle }}" @selected($categorie === $cle)>{{ $libelle }}</option>
            @endforeach
        </select>
        <select name="mode" style="min-width:10rem">
            <option value="">Tous les modes</option>
            @foreach ($modes as $cle => $libelle)
                <option value="{{ $cle }}" @selected($mode === $cle)>{{ $libelle }}</option>
            @endforeach
        </select>
        <input type="date" name="du" value="{{ $du }}" title="Du">
        <input type="date" name="au" value="{{ $au }}" title="Au">
        <button type="submit" class="btn btn-ghost">Filtrer</button>
        <a href="{{ route('depenses.index', $nature ? ['nature' => $nature] : []) }}" class="btn btn-ghost">Mois en cours</a>
    </form>

    <div class="card" style="overflow-x:auto">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Nature</th>
                    <th>Détail</th>
                    <th>Mode</th>
                    <th class="num">Montant</th>
                    <th>Par</th>
                    <th class="num">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($mouvements as $mouvement)
                    @php
                        // Une « entrée » dans ce journal est la contrepartie d'une annulation
                        $annulation = $mouvement->type === 'entree';
                        $source = $mouvement->reference_type;
                        $depense = $source === 'depense' ? ($depensesParId[$mouvement->reference_id] ?? null) : null;
                        $fraisLigne = $source === 'vehicule_frais' ? ($fraisParId[$mouvement->reference_id] ?? null) : null;
                        $depenseAnnulee = $depense && ! $annulation && $depense->statut === 'annulee';
                    @endphp
                    <tr style="{{ $depenseAnnulee ? 'opacity:.55' : '' }}">
                        <td>
                            <div>{{ $mouvement->date_operation->format('d/m/Y') }}</div>
                            <div class="person-sub">{{ $mouvement->date_operation->format('H:i') }}</div>
                        </td>
                        <td>
                            <span class="badge {{ $classesNature[$mouvement->categorie] ?? 'badge-gray' }}">
                                {{ $libelles[$mouvement->categorie] ?? $mouvement->categorie }}
                            </span>
                        </td>
                        <td>
                            @if ($depense && ! $annulation)
                                <div class="person-name" style="{{ $depenseAnnulee ? 'text-decoration:line-through' : '' }}">{{ $depense->libelle }}</div>
                                <div class="person-sub">
                                    {{ $categories[$depense->categorie] ?? $depense->categorie }}
                                    @if ($depense->reference) · Réf. {{ $depense->reference }} @endif
                                    @if ($depenseAnnulee) · annulée : {{ $depense->motif_annulation }} @endif
                                </div>
                            @elseif ($fraisLigne && $fraisLigne->vehicule)
                                <a href="{{ route('vehicules.show', $fraisLigne->vehicule) }}" class="person-name link">{{ $mouvement->description }}</a>
                            @elseif ($source === 'achat')
                                <a href="{{ route('achats.index') }}" class="person-name link">{{ $mouvement->description }}</a>
                            @else
                                <div class="person-name">{{ $mouvement->description ?: '—' }}</div>
                            @endif
                        </td>
                        <td>
                            <span class="badge {{ $mouvement->mode_paiement === 'orange_money' ? 'badge-yellow' : 'badge-gray' }}">
                                {{ $modes[$mouvement->mode_paiement] ?? $mouvement->mode_paiement }}
                            </span>
                        </td>
                        <td class="num" style="font-weight:800">
                            <span class="{{ $annulation ? 'text-green-700' : 'text-red-600' }}">{{ $annulation ? '+' : '−' }} {{ $fmt($mouvement->montant) }}</span>
                        </td>
                        <td>{{ $mouvement->user?->name ?? '—' }}</td>
                        <td class="num">
                            <div class="row-actions">
                                @if ($depense && ! $annulation && $depense->statut === 'valide')
                                    <button type="button" x-data=""
                                            x-on:click.prevent="$dispatch('open-modal', 'annuler-depense-{{ $depense->id }}')"
                                            class="icon-btn icon-btn-danger" title="Annuler cette dépense"><x-icon name="trash" /></button>
                                @elseif ($source === 'achat' && ! $annulation)
                                    <a href="{{ route('achats.index') }}" class="icon-btn icon-btn-view" title="Voir dans Achats (détail et annulation)"><x-icon name="eye" /></a>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="empty">Aucune dépense sur cette période.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <p class="muted" style="font-size:.78rem;margin-top:.6rem">
        Montants en FCFA. Ce journal regroupe tout l'argent dépensé : achats de véhicules, motos et pièces, frais de véhicules
        et dépenses de fonctionnement. Une annulation apparaît en vert et vient en déduction du total.
        Les achats s'enregistrent et s'annulent dans la rubrique Achats, les frais sur la fiche du véhicule.
    </p>

    <div class="mt-4">{{ $mouvements->links() }}</div>

    {{-- Fenêtre : nouvelle dépense de fonctionnement --}}
    <x-modal name="creer-depense" maxWidth="lg" :show="$actif && $errors->any()" focusable>
        <form method="POST" action="{{ route('depenses.store') }}" class="p-6"
              x-data="{ mode: @js($actif ? old('mode_paiement', 'especes') : 'especes') }">
            @csrf
            <input type="hidden" name="_modal" value="creer-depense">

            <h2 class="modal-title">Nouvelle dépense</h2>

            @include('partials.erreurs', ['modal' => 'creer-depense'])

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <x-input-label value="Libellé *" />
                    <x-text-input name="libelle" type="text" class="mt-1 block w-full" :value="$actif ? old('libelle') : ''" required />
                </div>

                <div>
                    <x-input-label value="Catégorie *" />
                    <select name="categorie" class="mt-1 block w-full">
                        @foreach ($categories as $cle => $libelle)
                            <option value="{{ $cle }}" @selected($actif && old('categorie') === $cle)>{{ $libelle }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <x-input-label value="Montant (FCFA) *" />
                    <input type="number" min="1" step="1" name="montant" value="{{ $actif ? old('montant') : '' }}" class="mt-1 block w-full" required>
                </div>

                <div>
                    <x-input-label value="Date *" />
                    <input type="date" name="date_depense" value="{{ $actif ? old('date_depense') : now()->toDateString() }}" class="mt-1 block w-full" required>
                </div>

                <div>
                    <x-input-label value="Mode de paiement *" />
                    <select name="mode_paiement" x-model="mode" class="mt-1 block w-full">
                        <option value="especes">Espèces</option>
                        <option value="orange_money">Orange Money</option>
                    </select>
                </div>

                <div class="sm:col-span-2" x-show="mode === 'orange_money'">
                    <x-input-label value="Référence de la transaction" />
                    <x-text-input name="reference" type="text" class="mt-1 block w-full" :value="$actif ? old('reference') : ''" />
                </div>

                <div class="sm:col-span-2">
                    <x-input-label value="Notes" />
                    <x-text-input name="notes" type="text" class="mt-1 block w-full" :value="$actif ? old('notes') : ''" />
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <x-secondary-button x-on:click="$dispatch('close')">Annuler</x-secondary-button>
                <x-primary-button>Enregistrer</x-primary-button>
            </div>
        </form>
    </x-modal>

    {{-- Fenêtres : annuler une dépense de fonctionnement (une par ligne valide) --}}
    @foreach ($mouvements->getCollection()->where('reference_type', 'depense')->where('type', 'sortie') as $mouvement)
        @php($depense = $depensesParId[$mouvement->reference_id] ?? null)

        @if ($depense && $depense->statut === 'valide')
            @php($nomModale = 'annuler-depense-' . $depense->id)

            <x-modal :name="$nomModale" maxWidth="md" :show="old('_modal') === $nomModale && $errors->any()" focusable>
                <form method="POST" action="{{ route('depenses.annuler', $depense) }}" class="p-6">
                    @csrf
                    <input type="hidden" name="_modal" value="{{ $nomModale }}">

                    <div class="confirm-icon"><x-icon name="warning" /></div>
                    <h2 class="modal-title" style="text-align:center;margin-bottom:.5rem">Annuler cette dépense ?</h2>
                    <p class="muted" style="font-size:.9rem;text-align:center">
                        « {{ $depense->libelle }} » ({{ $fmt($depense->montant) }} FCFA) sera annulée et le montant revient en caisse.
                    </p>

                    @include('partials.erreurs', ['modal' => $nomModale])

                    <div style="margin-top:1rem">
                        <x-input-label value="Motif de l'annulation *" />
                        <x-text-input name="motif" type="text" class="mt-1 block w-full" :value="old('_modal') === $nomModale ? old('motif') : ''" required />
                    </div>

                    <div class="mt-6 flex justify-center gap-3">
                        <x-secondary-button x-on:click="$dispatch('close')">Retour</x-secondary-button>
                        <button type="submit" class="btn btn-danger">Annuler la dépense</button>
                    </div>
                </form>
            </x-modal>
        @endif
    @endforeach
</x-app-layout>