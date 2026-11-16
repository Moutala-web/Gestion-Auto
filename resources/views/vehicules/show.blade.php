@php
    $categories = [
        'transport' => 'Transport',
        'douane' => 'Douane / importation',
        'reparation' => 'Réparation',
        'entretien' => 'Entretien',
        'lavage' => 'Lavage',
        'papiers' => 'Carte grise / papiers',
        'autres' => 'Autres frais',
    ];
    $modes = ['especes' => 'Espèces', 'orange_money' => 'Orange Money'];
    $marge = $vehicule->marge;
    $fraisActif = old('_modal') === 'ajouter-frais';
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="page-head">
            <div class="person">
                <span class="stat-icon stat-amber" style="width:3.4rem;height:3.4rem;border-radius:1rem"><x-icon name="truck" /></span>
                <div>
                    <h1 class="page-title">{{ $vehicule->marque }} {{ $vehicule->modele }}</h1>
                    <p class="page-sub">
                        {{ $vehicule->reference }} &nbsp;
                        @include('vehicules._statut', ['statut' => $vehicule->statut])
                    </p>
                </div>
            </div>
            <div class="page-actions">
                <a href="{{ route('vehicules.index') }}" class="btn btn-ghost"><x-icon name="arrow-left" /> Retour</a>
                <button type="button" x-data="" x-on:click.prevent="$dispatch('open-modal', 'supprimer-vehicule')" class="btn btn-ghost" style="color:#d92d34">
                    <x-icon name="trash" /> Supprimer
                </button>
                <button type="button" x-data="" x-on:click.prevent="$dispatch('open-modal', 'modifier-vehicule')" class="btn btn-primary">
                    <x-icon name="pencil" /> Modifier
                </button>
            </div>
        </div>
    </x-slot>

    @include('partials.messages')

    <div class="grid-2">
        {{-- Informations --}}
        <div class="card card-pad">
            <h3 class="card-title">Informations</h3>
            <dl class="kv">
                <div>
                    <dt>Modèle du catalogue</dt>
                    <dd>
                        @if ($vehicule->catalogue)
                            <a href="{{ route('modeles.index', ['type' => 'voiture', 'q' => $vehicule->catalogue->modele]) }}" class="link">{{ $vehicule->catalogue->nom_complet }}</a>
                            @if ($vehicule->catalogue->categorie) <span class="muted">· {{ $vehicule->catalogue->categorie }}</span> @endif
                        @else
                            —
                        @endif
                    </dd>
                </div>
                <div><dt>Numéro de châssis (VIN)</dt><dd>{{ $vehicule->numero_chassis ?: '—' }}</dd></div>
                <div><dt>Immatriculation</dt><dd>{{ $vehicule->immatriculation ?: '—' }}</dd></div>
                <div><dt>Année</dt><dd>{{ $vehicule->annee ?: '—' }}</dd></div>
                <div><dt>Couleur</dt><dd>{{ $vehicule->couleur ?: '—' }}</dd></div>
                <div><dt>Kilométrage</dt><dd>{{ number_format($vehicule->kilometrage, 0, ',', ' ') }} km</dd></div>
                <div><dt>Description</dt><dd>{{ $vehicule->description ?: '—' }}</dd></div>
            </dl>
        </div>

        <div class="stack">
            {{-- Synthèse financière --}}
            <div class="card card-pad">
                <h3 class="card-title">Synthèse financière <span class="muted" style="font-weight:500">(FCFA)</span></h3>
                <dl class="kv" style="grid-template-columns:repeat(auto-fit,minmax(130px,1fr))">
                    <div><dt>Prix d'achat</dt><dd style="font-size:1.25rem;font-weight:800">{{ number_format($vehicule->prix_achat, 0, ',', ' ') }}</dd></div>
                    <div><dt>Total des frais</dt><dd style="font-size:1.25rem;font-weight:800">{{ number_format($vehicule->total_frais, 0, ',', ' ') }}</dd></div>
                    <div><dt>Coût total</dt><dd style="font-size:1.25rem;font-weight:800">{{ number_format($vehicule->cout_total, 0, ',', ' ') }}</dd></div>
                    <div><dt>Prix de vente</dt><dd style="font-size:1.25rem;font-weight:800">{{ $vehicule->prix_vente !== null ? number_format($vehicule->prix_vente, 0, ',', ' ') : '—' }}</dd></div>
                    <div>
                        <dt>Marge prévisionnelle</dt>
                        <dd style="font-size:1.25rem;font-weight:800">
                            @if ($marge === null)
                                —
                            @else
                                <span class="{{ $marge < 0 ? 'text-red-600' : 'text-green-700' }}">{{ number_format($marge, 0, ',', ' ') }}</span>
                            @endif
                        </dd>
                    </div>
                </dl>
            </div>

            {{-- Frais --}}
            <div class="card">
                <div class="card-pad" style="padding-bottom:.75rem;display:flex;align-items:center;justify-content:space-between;gap:1rem">
                    <h3 class="card-title" style="margin:0">Frais</h3>
                    @if ($vehicule->statut !== 'vendu')
                        <button type="button" x-data="" x-on:click.prevent="$dispatch('open-modal', 'ajouter-frais')" class="btn btn-primary">
                            <x-icon name="plus" /> Ajouter un frais
                        </button>
                    @endif
                </div>

                <div style="overflow-x:auto">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Libellé</th>
                                <th>Catégorie</th>
                                <th>Paiement</th>
                                <th class="num">Montant</th>
                                <th class="num"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($vehicule->frais as $frais)
                                <tr>
                                    <td>{{ $frais->date_frais->format('d/m/Y') }}</td>
                                    <td>
                                        <div class="person-name">{{ $frais->libelle }}</div>
                                        @if ($frais->description)
                                            <div class="person-sub">{{ $frais->description }}</div>
                                        @endif
                                    </td>
                                    <td>{{ $categories[$frais->categorie] ?? $frais->categorie }}</td>
                                    <td>
                                        <span class="badge {{ $frais->mode_paiement === 'orange_money' ? 'badge-yellow' : 'badge-gray' }}">
                                            {{ $modes[$frais->mode_paiement] ?? $frais->mode_paiement }}
                                        </span>
                                    </td>
                                    <td class="num" style="font-weight:700">{{ number_format($frais->montant, 0, ',', ' ') }}</td>
                                    <td class="num">
                                        @if ($vehicule->statut !== 'vendu')
                                            <x-delete-modal :action="route('vehicules.frais.destroy', [$vehicule, $frais])"
                                                            titre="Supprimer ce frais ?"
                                                            :message="$frais->libelle . ' (' . number_format($frais->montant, 0, ',', ' ') . ' FCFA) sera retiré du coût du véhicule.'" />
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="empty">Aucun frais enregistré.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- Fenêtre : modifier le véhicule --}}
    <x-modal name="modifier-vehicule" :show="old('_modal') === 'modifier-vehicule' && $errors->any()" focusable>
        <form method="POST" action="{{ route('vehicules.update', $vehicule) }}" class="p-6">
            @method('PUT')
            <h2 class="modal-title">Modifier le véhicule</h2>

            @include('vehicules._form', ['modal' => 'modifier-vehicule', 'vehicule' => $vehicule])

            <div class="mt-6 flex justify-end gap-3">
                <x-secondary-button x-on:click="$dispatch('close')">Annuler</x-secondary-button>
                <x-primary-button>Mettre à jour</x-primary-button>
            </div>
        </form>
    </x-modal>

    {{-- Fenêtre : supprimer le véhicule --}}
    <x-modal name="supprimer-vehicule" maxWidth="md">
        <form method="POST" action="{{ route('vehicules.destroy', $vehicule) }}" class="p-6 text-center">
            @csrf
            @method('DELETE')

            <div class="confirm-icon"><x-icon name="trash" /></div>
            <h2 class="modal-title" style="margin-bottom:.5rem">Supprimer ce véhicule ?</h2>
            <p class="muted" style="font-size:.9rem">
                {{ $vehicule->marque }} {{ $vehicule->modele }} ({{ $vehicule->reference }}) sera supprimé définitivement.
                S'il a des frais, des locations ou des ventes, la suppression sera refusée.
            </p>

            <div class="mt-6 flex justify-center gap-3">
                <x-secondary-button x-on:click="$dispatch('close')">Annuler</x-secondary-button>
                <button type="submit" class="btn btn-danger">Supprimer</button>
            </div>
        </form>
    </x-modal>

    {{-- Fenêtre : ajouter un frais --}}
    @if ($vehicule->statut !== 'vendu')
        <x-modal name="ajouter-frais" :show="$fraisActif && $errors->any()" focusable>
            <form method="POST" action="{{ route('vehicules.frais.store', $vehicule) }}" class="p-6">
                @csrf
                <input type="hidden" name="_modal" value="ajouter-frais">
                <h2 class="modal-title">Ajouter un frais</h2>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <x-input-label value="Libellé *" />
                        <x-text-input name="libelle" type="text" class="mt-1 block w-full" :value="$fraisActif ? old('libelle') : ''" required />
                        <x-input-error :messages="$fraisActif ? $errors->get('libelle') : []" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label value="Catégorie *" />
                        <select name="categorie" class="mt-1 block w-full">
                            @foreach ($categories as $valeur => $libelle)
                                <option value="{{ $valeur }}" @selected($fraisActif && old('categorie') === $valeur)>{{ $libelle }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$fraisActif ? $errors->get('categorie') : []" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label value="Montant (FCFA) *" />
                        <x-text-input name="montant" type="number" min="1" step="1" class="mt-1 block w-full" :value="$fraisActif ? old('montant') : ''" required />
                        <x-input-error :messages="$fraisActif ? $errors->get('montant') : []" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label value="Mode de paiement *" />
                        <select name="mode_paiement" class="mt-1 block w-full">
                            @foreach ($modes as $valeur => $libelle)
                                <option value="{{ $valeur }}" @selected($fraisActif && old('mode_paiement') === $valeur)>{{ $libelle }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$fraisActif ? $errors->get('mode_paiement') : []" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label value="Date *" />
                        <x-text-input name="date_frais" type="date" class="mt-1 block w-full" :value="$fraisActif ? old('date_frais', now()->toDateString()) : now()->toDateString()" required />
                        <x-input-error :messages="$fraisActif ? $errors->get('date_frais') : []" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label value="Détails" />
                        <x-text-input name="description" type="text" class="mt-1 block w-full" :value="$fraisActif ? old('description') : ''" />
                        <x-input-error :messages="$fraisActif ? $errors->get('description') : []" class="mt-2" />
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <x-secondary-button x-on:click="$dispatch('close')">Annuler</x-secondary-button>
                    <x-primary-button>Ajouter le frais</x-primary-button>
                </div>
            </form>
        </x-modal>
    @endif
</x-app-layout>