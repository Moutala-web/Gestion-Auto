@php
    $statuts = [
        'reservee' => ['Réservée', 'badge-yellow'],
        'en_cours' => ['En cours', 'badge-blue'],
        'terminee' => ['Terminée', 'badge-green'],
        'annulee' => ['Annulée', 'badge-red'],
    ];
    $modes = ['especes' => 'Espèces', 'orange_money' => 'Orange Money'];
    $typesPaiement = ['location' => 'Location', 'caution' => 'Caution'];

    [$libelleStatut, $classeStatut] = $statuts[$location->statut] ?? [$location->statut, 'badge-gray'];
    $enRetard = $location->statut === 'en_cours' && now()->greaterThan($location->date_retour_prevue);
    $ouverte = in_array($location->statut, ['reservee', 'en_cours'], true);

    // Calculés une seule fois (chaque accesseur interroge la base)
    $payeLocation = $location->total_paye;
    $resteTarif = max((float) $location->tarif - $payeLocation, 0);
    $verseeCaution = $location->caution_versee;
    $resteCaution = max((float) $location->caution - $verseeCaution, 0);
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="page-head">
            <div>
                <h1 class="page-title">Location {{ $location->numero }}</h1>
                <p class="page-sub">
                    <span class="badge {{ $classeStatut }}">{{ $libelleStatut }}</span>
                    @if ($enRetard)
                        <span class="badge badge-red">En retard</span>
                    @endif
                </p>
            </div>
            <div class="page-actions">
                <a href="{{ route('locations.index') }}" class="btn btn-ghost"><x-icon name="arrow-left" /> Retour</a>

                @if ($location->statut === 'reservee')
                    <button type="button" x-data="" x-on:click.prevent="$dispatch('open-modal', 'annuler-location')" class="btn btn-ghost" style="color:#d92d34">
                        <x-icon name="trash" /> Annuler
                    </button>
                @endif

                @if ($ouverte)
                    <button type="button" x-data="" x-on:click.prevent="$dispatch('open-modal', 'encaisser')" class="btn btn-ghost">
                        <x-icon name="plus" /> Encaisser
                    </button>
                @endif

                @if ($location->statut === 'reservee')
                    <button type="button" x-data="" x-on:click.prevent="$dispatch('open-modal', 'demarrer-location')" class="btn btn-primary">
                        <x-icon name="check-circle" /> Démarrer la location
                    </button>
                @elseif ($location->statut === 'en_cours')
                    <button type="button" x-data="" x-on:click.prevent="$dispatch('open-modal', 'terminer-location')" class="btn btn-primary">
                        <x-icon name="check-circle" /> Enregistrer le retour
                    </button>
                @endif
            </div>
        </div>
    </x-slot>

    @include('partials.messages')

    <div class="grid-2">
        <div class="stack">
            <div class="card card-pad">
                <h3 class="card-title">Contrat</h3>
                <dl class="kv">
                    <div>
                        <dt>Client</dt>
                        <dd><a href="{{ route('clients.show', $location->client) }}" class="link">{{ $location->client->nom_complet }}</a> · {{ $location->client->telephone }}</dd>
                    </div>
                    <div>
                        <dt>Véhicule</dt>
                        <dd><a href="{{ route('vehicules.show', $location->vehicule) }}" class="link">{{ $location->vehicule->marque }} {{ $location->vehicule->modele }}</a> · {{ $location->vehicule->reference }}</dd>
                    </div>
                    <div><dt>Départ prévu</dt><dd>{{ $location->date_depart->format('d/m/Y à H:i') }}</dd></div>
                    <div><dt>Retour prévu</dt><dd>{{ $location->date_retour_prevue->format('d/m/Y à H:i') }}</dd></div>
                    @if ($location->date_retour_reelle)
                        <div><dt>Retour réel</dt><dd>{{ $location->date_retour_reelle->format('d/m/Y à H:i') }}</dd></div>
                    @endif
                    <div><dt>Kilométrage au départ</dt><dd>{{ number_format($location->kilometrage_depart, 0, ',', ' ') }} km</dd></div>
                    @if ($location->kilometrage_retour !== null)
                        <div>
                            <dt>Kilométrage au retour</dt>
                            <dd>{{ number_format($location->kilometrage_retour, 0, ',', ' ') }} km
                                ({{ number_format($location->kilometrage_retour - $location->kilometrage_depart, 0, ',', ' ') }} km parcourus)</dd>
                        </div>
                    @endif
                    <div><dt>État au départ</dt><dd>{{ $location->etat_depart ?: '—' }}</dd></div>
                    <div><dt>État au retour</dt><dd>{{ $location->etat_retour ?: '—' }}</dd></div>
                    <div><dt>Enregistrée par</dt><dd>{{ $location->user?->name ?? '—' }}</dd></div>
                    <div><dt>Notes</dt><dd style="white-space:pre-line">{{ $location->notes ?: '—' }}</dd></div>
                </dl>
            </div>
        </div>

        <div class="stack">
            <div class="card card-pad">
                <h3 class="card-title">Location <span class="muted" style="font-weight:500">(FCFA)</span></h3>
                <dl class="kv" style="grid-template-columns:repeat(auto-fit,minmax(130px,1fr))">
                    <div><dt>Tarif convenu</dt><dd style="font-size:1.25rem;font-weight:800">{{ number_format($location->tarif, 0, ',', ' ') }}</dd></div>
                    <div><dt>Déjà payé</dt><dd style="font-size:1.25rem;font-weight:800">{{ number_format($payeLocation, 0, ',', ' ') }}</dd></div>
                    <div>
                        <dt>Reste à payer</dt>
                        <dd style="font-size:1.25rem;font-weight:800" class="{{ $resteTarif > 0 ? 'text-red-600' : 'text-green-700' }}">{{ number_format($resteTarif, 0, ',', ' ') }}</dd>
                    </div>
                </dl>
            </div>

            <div class="card card-pad">
                <h3 class="card-title">Caution <span class="muted" style="font-weight:500">(FCFA · somme détenue, pas une recette)</span></h3>
                <dl class="kv" style="grid-template-columns:repeat(auto-fit,minmax(130px,1fr))">
                    <div><dt>Caution convenue</dt><dd style="font-size:1.25rem;font-weight:800">{{ number_format($location->caution, 0, ',', ' ') }}</dd></div>
                    <div><dt>Versée</dt><dd style="font-size:1.25rem;font-weight:800">{{ number_format($verseeCaution, 0, ',', ' ') }}</dd></div>
                    <div><dt>Retenue</dt><dd style="font-size:1.25rem;font-weight:800">{{ number_format($location->retenue_caution, 0, ',', ' ') }}</dd></div>
                    <div><dt>Remboursée</dt><dd style="font-size:1.25rem;font-weight:800">{{ number_format($location->caution_remboursee, 0, ',', ' ') }}</dd></div>
                </dl>
            </div>

            <div class="card">
                <div class="card-pad" style="padding-bottom:.75rem">
                    <h3 class="card-title" style="margin:0">Paiements</h3>
                </div>

                <div style="overflow-x:auto">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Objet</th>
                                <th>Mode</th>
                                <th class="num">Montant</th>
                                <th>Reçu</th>
                                <th>Statut</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($location->paiements as $paiement)
                                <tr>
                                    <td>{{ $paiement->date_paiement->format('d/m/Y H:i') }}</td>
                                    <td>
                                        <span class="badge {{ $paiement->type === 'caution' ? 'badge-blue' : 'badge-green' }}">{{ $typesPaiement[$paiement->type] ?? $paiement->type }}</span>
                                    </td>
                                    <td>
                                        {{ $modes[$paiement->mode] ?? $paiement->mode }}
                                        @if ($paiement->reference_transaction)
                                            <div class="person-sub">Réf. {{ $paiement->reference_transaction }}</div>
                                        @endif
                                    </td>
                                    <td class="num" style="font-weight:700">{{ number_format($paiement->montant, 0, ',', ' ') }}</td>
                                    <td>
                                        @forelse ($paiement->recus as $recu)
                                            <a href="{{ route('recus.pdf', $recu) }}" target="_blank" class="link">{{ $recu->numero }}</a>
                                        @empty
                                            —
                                        @endforelse
                                    </td>
                                    <td>
                                        <span class="badge {{ $paiement->statut === 'valide' ? 'badge-green' : 'badge-red' }}">{{ $paiement->statut === 'valide' ? 'Valide' : 'Annulé' }}</span>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="empty">Aucun paiement enregistré.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- Fenêtre : encaisser un paiement --}}
    @if ($ouverte)
        <x-modal name="encaisser" maxWidth="lg" :show="old('_modal') === 'encaisser' && $errors->any()" focusable>
            <form method="POST" action="{{ route('locations.paiements.store', $location) }}" class="p-6"
                  x-data="{
                      type: @js(old('type', $resteTarif > 0 ? 'location' : 'caution')),
                      restes: { location: @js((float) $resteTarif), caution: @js((float) $resteCaution) },
                      mode: @js(old('mode', 'especes')),
                      montant: @js(old('montant', $resteTarif > 0 ? (float) $resteTarif : (float) $resteCaution)),
                  }">
                @csrf
                <input type="hidden" name="_modal" value="encaisser">

                <h2 class="modal-title">Encaisser un paiement</h2>

                @include('partials.erreurs', ['modal' => 'encaisser'])

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label value="Objet du paiement *" />
                        <select name="type" x-model="type" x-on:change="montant = restes[type]" class="mt-1 block w-full">
                            <option value="location">Tarif de location</option>
                            <option value="caution">Caution</option>
                        </select>
                        <p class="muted" style="font-size:.75rem;margin-top:.25rem">
                            Reste à encaisser :
                            <strong x-text="new Intl.NumberFormat('fr-FR').format(restes[type]) + ' FCFA'"></strong>
                        </p>
                    </div>

                    <div>
                        <x-input-label value="Montant (FCFA) *" />
                        <input type="number" min="1" step="1" name="montant" x-model.number="montant" class="mt-1 block w-full" required>
                    </div>

                    <div>
                        <x-input-label value="Mode de paiement *" />
                        <select name="mode" x-model="mode" class="mt-1 block w-full">
                            <option value="especes">Espèces</option>
                            <option value="orange_money">Orange Money</option>
                        </select>
                    </div>

                    <div x-show="mode === 'orange_money'">
                        <x-input-label value="Référence de la transaction *" />
                        <x-text-input name="reference_transaction" type="text" class="mt-1 block w-full" :value="old('reference_transaction')" />
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <x-secondary-button x-on:click="$dispatch('close')">Annuler</x-secondary-button>
                    <x-primary-button>Enregistrer le paiement</x-primary-button>
                </div>
            </form>
        </x-modal>
    @endif

    {{-- Fenêtre : démarrer la location --}}
    @if ($location->statut === 'reservee')
        <x-modal name="demarrer-location" maxWidth="lg" :show="old('_modal') === 'demarrer-location' && $errors->any()" focusable>
            <form method="POST" action="{{ route('locations.demarrer', $location) }}" class="p-6">
                @csrf
                <input type="hidden" name="_modal" value="demarrer-location">

                <h2 class="modal-title" style="margin-bottom:.25rem">Remise du véhicule</h2>
                <p class="muted" style="font-size:.85rem;margin:0 0 1rem">Le véhicule passera au statut « En location ».</p>

                @include('partials.erreurs', ['modal' => 'demarrer-location'])

                @if ($resteCaution > 0)
                    <div class="flash flash-error" style="align-items:flex-start">
                        <x-icon name="warning" />
                        <span>La caution n'est pas entièrement versée : il reste {{ number_format($resteCaution, 0, ',', ' ') }} FCFA. Utilise « Encaisser » avant de remettre le véhicule.</span>
                    </div>
                @endif

                <div class="grid grid-cols-1 gap-4">
                    <div>
                        <x-input-label value="Kilométrage au départ *" />
                        <input type="number" min="0" name="kilometrage_depart" value="{{ old('kilometrage_depart', $location->kilometrage_depart) }}" class="mt-1 block w-full" required>
                    </div>

                    <div>
                        <x-input-label value="État du véhicule au départ" />
                        <x-text-input name="etat_depart" type="text" class="mt-1 block w-full" :value="old('etat_depart', $location->etat_depart)" />
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <x-secondary-button x-on:click="$dispatch('close')">Annuler</x-secondary-button>
                    <x-primary-button>Démarrer la location</x-primary-button>
                </div>
            </form>
        </x-modal>
    @endif

    {{-- Fenêtre : enregistrer le retour --}}
    @if ($location->statut === 'en_cours')
        <x-modal name="terminer-location" maxWidth="lg" :show="old('_modal') === 'terminer-location' && $errors->any()" focusable>
            <form method="POST" action="{{ route('locations.terminer', $location) }}" class="p-6"
                  x-data="{
                      versee: @js((float) $verseeCaution),
                      retenue: @js((float) old('retenue_caution', 0)),
                      fmt(n) { return new Intl.NumberFormat('fr-FR').format(Math.round(n)); },
                  }">
                @csrf
                <input type="hidden" name="_modal" value="terminer-location">

                <h2 class="modal-title" style="margin-bottom:.25rem">Retour du véhicule</h2>
                <p class="muted" style="font-size:.85rem;margin:0 0 1rem">La location sera clôturée et le véhicule redeviendra disponible.</p>

                @include('partials.erreurs', ['modal' => 'terminer-location'])

                @if ($resteTarif > 0)
                    <div class="flash flash-error" style="align-items:flex-start">
                        <x-icon name="warning" />
                        <span>Le tarif n'est pas entièrement payé : il reste {{ number_format($resteTarif, 0, ',', ' ') }} FCFA. Encaisse le solde avant d'enregistrer le retour.</span>
                    </div>
                @endif

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label value="Kilométrage au retour *" />
                        <input type="number" min="{{ $location->kilometrage_depart }}" name="kilometrage_retour" value="{{ old('kilometrage_retour', $location->kilometrage_depart) }}" class="mt-1 block w-full" required>
                    </div>

                    <div>
                        <x-input-label value="État du véhicule au retour" />
                        <x-text-input name="etat_retour" type="text" class="mt-1 block w-full" :value="old('etat_retour')" />
                    </div>

                    <div>
                        <x-input-label value="Retenue sur la caution (FCFA)" />
                        <input type="number" min="0" step="1" name="retenue_caution" x-model.number="retenue" class="mt-1 block w-full">
                        <p class="muted" style="font-size:.75rem;margin-top:.25rem">À remplir en cas de dégâts.</p>
                    </div>

                    <div>
                        <x-input-label value="Remboursement fait en *" />
                        <select name="mode_remboursement" class="mt-1 block w-full">
                            <option value="especes" @selected(old('mode_remboursement', 'especes') === 'especes')>Espèces</option>
                            <option value="orange_money" @selected(old('mode_remboursement') === 'orange_money')>Orange Money</option>
                        </select>
                    </div>
                </div>

                <div style="margin-top:1.25rem;border-radius:.9rem;background:#0b1220;color:#fff;padding:1rem 1.25rem">
                    <div style="display:flex;justify-content:space-between;font-size:.88rem;color:#aab4cf">
                        <span>Caution versée</span><span x-text="fmt(versee) + ' FCFA'"></span>
                    </div>
                    <div style="display:flex;justify-content:space-between;font-size:.88rem;color:#aab4cf;margin-top:.25rem">
                        <span>Retenue</span><span x-text="'− ' + fmt(retenue || 0) + ' FCFA'"></span>
                    </div>
                    <div style="display:flex;justify-content:space-between;align-items:baseline;margin-top:.6rem;padding-top:.6rem;border-top:1px solid rgba(255,255,255,.14)">
                        <span style="font-weight:700">À rembourser au client</span>
                        <span style="font-size:1.4rem;font-weight:800;color:#f5a524" x-text="fmt(Math.max(versee - (retenue || 0), 0)) + ' FCFA'"></span>
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <x-secondary-button x-on:click="$dispatch('close')">Annuler</x-secondary-button>
                    <x-primary-button>Clôturer la location</x-primary-button>
                </div>
            </form>
        </x-modal>
    @endif

    {{-- Fenêtre : annuler la réservation --}}
    @if ($location->statut === 'reservee')
        <x-modal name="annuler-location" maxWidth="md" :show="old('_modal') === 'annuler-location' && $errors->any()" focusable>
            <form method="POST" action="{{ route('locations.annuler', $location) }}" class="p-6">
                @csrf
                <input type="hidden" name="_modal" value="annuler-location">

                <div class="confirm-icon"><x-icon name="warning" /></div>
                <h2 class="modal-title" style="text-align:center;margin-bottom:.5rem">Annuler la location {{ $location->numero }} ?</h2>
                <p class="muted" style="font-size:.9rem;text-align:center">
                    Tout ce qui a été encaissé (location et caution) est remboursé et enregistré en caisse.
                </p>

                @include('partials.erreurs', ['modal' => 'annuler-location'])

                <div style="margin-top:1rem">
                    <x-input-label value="Motif de l'annulation *" />
                    <x-text-input name="motif" type="text" class="mt-1 block w-full" :value="old('motif')" required />
                </div>

                <div class="mt-6 flex justify-center gap-3">
                    <x-secondary-button x-on:click="$dispatch('close')">Retour</x-secondary-button>
                    <button type="submit" class="btn btn-danger">Annuler la location</button>
                </div>
            </form>
        </x-modal>
    @endif
</x-app-layout>