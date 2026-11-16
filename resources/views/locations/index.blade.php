@php
    $statuts = [
        'reservee' => ['Réservée', 'badge-yellow'],
        'en_cours' => ['En cours', 'badge-blue'],
        'terminee' => ['Terminée', 'badge-green'],
        'annulee' => ['Annulée', 'badge-red'],
    ];
    $actif = old('_modal') === 'creer-location';
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="page-head">
            <div>
                <h1 class="page-title">Locations</h1>
                <p class="page-sub">{{ $locations->total() }} contrat(s) enregistré(s)</p>
            </div>
            <div class="page-actions">
                <button type="button" x-data="" x-on:click.prevent="$dispatch('open-modal', 'creer-location')" class="btn btn-primary">
                    <x-icon name="plus" /> Nouvelle location
                </button>
            </div>
        </div>
    </x-slot>

    @include('partials.messages')

    <form method="GET" action="{{ route('locations.index') }}" class="toolbar">
        <div class="search">
            <x-icon name="search" />
            <input type="text" name="q" value="{{ $recherche }}" placeholder="N° de location, client ou véhicule">
        </div>
        <select name="statut" style="min-width:11rem">
            <option value="">Tous les statuts</option>
            @foreach ($statuts as $valeur => [$libelle])
                <option value="{{ $valeur }}" @selected($statut === $valeur)>{{ $libelle }}</option>
            @endforeach
        </select>
        <button type="submit" class="btn btn-ghost">Filtrer</button>
        @if ($recherche !== '' || $statut)
            <a href="{{ route('locations.index') }}" class="btn btn-ghost">Effacer</a>
        @endif
    </form>

    <div class="card" style="overflow-x:auto">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Location</th>
                    <th>Client</th>
                    <th>Véhicule</th>
                    <th>Période</th>
                    <th class="num">Tarif</th>
                    <th>Statut</th>
                    <th class="num">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($locations as $location)
                    @php
                        [$libelle, $classe] = $statuts[$location->statut] ?? [$location->statut, 'badge-gray'];
                        $enRetard = $location->statut === 'en_cours' && now()->greaterThan($location->date_retour_prevue);
                    @endphp
                    <tr>
                        <td><a href="{{ route('locations.show', $location) }}" class="person-name link">{{ $location->numero }}</a></td>
                        <td>
                            <div class="person">
                                <span class="avatar avatar-soft">{{ mb_strtoupper(mb_substr($location->client->nom, 0, 1)) }}</span>
                                <div>
                                    <div class="person-name">{{ $location->client->nom_complet }}</div>
                                    <div class="person-sub">{{ $location->client->telephone }}</div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="person-name">{{ $location->vehicule->marque }} {{ $location->vehicule->modele }}</div>
                            <div class="person-sub">{{ $location->vehicule->reference }}</div>
                        </td>
                        <td>
                            <div>{{ $location->date_depart->format('d/m/Y H:i') }}</div>
                            <div class="person-sub">→ {{ $location->date_retour_prevue->format('d/m/Y H:i') }}</div>
                        </td>
                        <td class="num" style="font-weight:800">{{ number_format($location->tarif, 0, ',', ' ') }}</td>
                        <td>
                            <span class="badge {{ $classe }}">{{ $libelle }}</span>
                            @if ($enRetard)
                                <span class="badge badge-red" style="margin-top:.25rem">En retard</span>
                            @endif
                        </td>
                        <td class="num">
                            <div class="row-actions">
                                <a href="{{ route('locations.show', $location) }}" class="icon-btn icon-btn-view" title="Voir la location"><x-icon name="eye" /></a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="empty">Aucune location trouvée.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <p class="muted" style="font-size:.78rem;margin-top:.6rem">Montants en FCFA. Le tarif est le montant total convenu pour toute la période.</p>

    <div class="mt-4">{{ $locations->links() }}</div>

    {{-- Fenêtre : nouvelle location --}}
    <x-modal name="creer-location" maxWidth="2xl" :show="$actif && $errors->any()" focusable>
        <form method="POST" action="{{ route('locations.store') }}" class="p-6"
              x-data="{
                  vehicules: @js($vehicules),
                  vehicule: @js($actif ? (string) old('vehicule_id') : ''),
                  km: @js($actif ? (int) old('kilometrage_depart', 0) : 0),
                  debut: @js($actif ? old('date_depart') : now()->format('Y-m-d\TH:i')),
                  fin: @js($actif ? old('date_retour_prevue') : ''),
                  choisir() {
                      const v = this.vehicules.find(x => String(x.id) === String(this.vehicule));
                      this.km = v ? v.km : 0;
                  },
                  get jours() {
                      if (!this.debut || !this.fin) return 0;
                      const d = (new Date(this.fin) - new Date(this.debut)) / 86400000;
                      return d > 0 ? Math.ceil(d) : 0;
                  },
              }">
            @csrf
            <input type="hidden" name="_modal" value="creer-location">

            <h2 class="modal-title">Nouvelle location</h2>

            @include('partials.erreurs', ['modal' => 'creer-location'])

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <x-input-label value="Client *" />
                    <select name="client_id" class="mt-1 block w-full" required>
                        <option value="">Choisir un client...</option>
                        @foreach ($clients as $client)
                            <option value="{{ $client->id }}" @selected($actif && (int) old('client_id') === $client->id)>
                                {{ $client->nom_complet }} · {{ $client->telephone }}
                            </option>
                        @endforeach
                    </select>
                    @if ($clients->isEmpty())
                        <p class="muted" style="font-size:.78rem;margin-top:.3rem">Aucun client : crée d'abord un client.</p>
                    @endif
                </div>

                <div>
                    <x-input-label value="Véhicule *" />
                    <select name="vehicule_id" x-model="vehicule" x-on:change="choisir()" class="mt-1 block w-full" required>
                        <option value="">Choisir un véhicule...</option>
                        <template x-for="v in vehicules" :key="v.id">
                            <option :value="v.id" x-text="v.label"></option>
                        </template>
                    </select>
                </div>

                <div>
                    <x-input-label value="Départ *" />
                    <input type="datetime-local" name="date_depart" x-model="debut" class="mt-1 block w-full" required>
                </div>

                <div>
                    <x-input-label value="Retour prévu *" />
                    <input type="datetime-local" name="date_retour_prevue" x-model="fin" class="mt-1 block w-full" required>
                    <p class="muted" style="font-size:.75rem;margin-top:.25rem" x-show="jours > 0" x-text="jours + ' jour(s)'"></p>
                </div>

                <div>
                    <x-input-label value="Tarif total convenu (FCFA) *" />
                    <input type="number" min="1" step="1" name="tarif" value="{{ $actif ? old('tarif') : '' }}" class="mt-1 block w-full" required>
                </div>

                <div>
                    <x-input-label value="Caution (FCFA)" />
                    <input type="number" min="0" step="1" name="caution" value="{{ $actif ? old('caution', 0) : 0 }}" class="mt-1 block w-full">
                </div>

                <div>
                    <x-input-label value="Kilométrage au départ" />
                    <input type="number" min="0" name="kilometrage_depart" x-model.number="km" class="mt-1 block w-full">
                </div>

                <div>
                    <x-input-label value="État du véhicule au départ" />
                    <x-text-input name="etat_depart" type="text" class="mt-1 block w-full" :value="$actif ? old('etat_depart') : ''" />
                </div>

                <div class="sm:col-span-2">
                    <x-input-label value="Notes" />
                    <x-text-input name="notes" type="text" class="mt-1 block w-full" :value="$actif ? old('notes') : ''" />
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <x-secondary-button x-on:click="$dispatch('close')">Annuler</x-secondary-button>
                <x-primary-button>Enregistrer la réservation</x-primary-button>
            </div>
        </form>
    </x-modal>
</x-app-layout>