@php
    $modes = ['especes' => 'Espèces', 'orange_money' => 'Orange Money'];
    $types = [
        'vehicule' => ['Véhicule', 'badge-yellow'],
        'moto' => ['Moto', 'badge-blue'],
        'piece' => ['Pièce', 'badge-green'],
        'divers' => ['Article divers', 'badge-blue'],
        'service' => ['Prestation', 'badge-gray'],
    ];
    $validee = $vente->estValidee();
    $annulationActive = old('_modal') === 'annuler-vente';
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="page-head">
            <div>
                <h1 class="page-title">Vente {{ $vente->numero }}</h1>
                <p class="page-sub">
                    {{ $vente->date_vente->format('d/m/Y à H:i') }} &nbsp;
                    <span class="badge {{ $validee ? 'badge-green' : 'badge-red' }}">{{ $validee ? 'Validée' : 'Annulée' }}</span>
                </p>
            </div>
            <div class="page-actions">
                <a href="{{ route('ventes.index') }}" class="btn btn-ghost"><x-icon name="arrow-left" /> Retour</a>
                @if ($validee)
                    <button type="button" x-data="" x-on:click.prevent="$dispatch('open-modal', 'annuler-vente')" class="btn btn-ghost" style="color:#d92d34">
                        <x-icon name="trash" /> Annuler la vente
                    </button>
                @endif
            </div>
        </div>
    </x-slot>

    @include('partials.messages')

    <div class="grid-2">
        <div class="stack">
            <div class="card card-pad">
                <h3 class="card-title">Client</h3>
                <div class="person" style="margin-bottom:1rem">
                    <span class="avatar avatar-soft avatar-lg">{{ mb_strtoupper(mb_substr($vente->client->nom, 0, 1)) }}</span>
                    <div>
                        <a href="{{ route('clients.show', $vente->client) }}" class="person-name link">{{ $vente->client->nom_complet }}</a>
                        <div class="person-sub">{{ $vente->client->telephone }}</div>
                    </div>
                </div>
                <dl class="kv">
                    <div><dt>Enregistrée par</dt><dd>{{ $vente->user?->name ?? '—' }}</dd></div>
                    <div><dt>Notes</dt><dd style="white-space:pre-line">{{ $vente->notes ?: '—' }}</dd></div>
                </dl>
            </div>

            <div class="card card-pad">
                <h3 class="card-title">Reçus</h3>
                @forelse ($vente->recus as $recu)
                    <div class="list-row">
                        <div>
                            <div class="list-main">{{ $recu->numero }}</div>
                            <div class="list-sub">Émis le {{ $recu->date_generation->format('d/m/Y') }}</div>
                        </div>
                        <div class="row-actions">
                            <a href="{{ route('recus.pdf', $recu) }}" target="_blank" class="btn btn-ghost" style="padding:.4rem .8rem">
                                <x-icon name="document" /> Ouvrir
                            </a>
                            <a href="{{ route('recus.pdf', ['recu' => $recu, 'telecharger' => 1]) }}" class="icon-btn icon-btn-view" title="Télécharger le PDF">
                                <x-icon name="download" />
                            </a>
                        </div>
                    </div>
                @empty
                    <p class="muted" style="font-size:.9rem">Aucun reçu.</p>
                @endforelse
            </div>
        </div>

        <div class="stack">
            <div class="card">
                <div class="card-pad" style="padding-bottom:.75rem">
                    <h3 class="card-title" style="margin:0">Articles et prestations</h3>
                </div>

                <div style="overflow-x:auto">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Désignation</th>
                                <th class="num">Quantité</th>
                                <th class="num">Prix unitaire</th>
                                <th class="num">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($vente->details as $detail)
                                @php
                                    // Un article divers est enregistré comme une pièce : on le distingue par sa famille
                                    $cle = $detail->type_article === 'piece' && $detail->piece?->famille === 'divers' ? 'divers' : $detail->type_article;
                                    [$typeLibelle, $typeClasse] = $types[$cle] ?? [$detail->type_article, 'badge-gray'];
                                @endphp
                                <tr>
                                    <td>
                                        <div class="person-name">{{ $detail->designation }}</div>
                                        <span class="badge {{ $typeClasse }}" style="margin-top:.25rem">{{ $typeLibelle }}</span>
                                    </td>
                                    <td class="num">{{ $detail->quantite }}</td>
                                    <td class="num">{{ number_format($detail->prix_unitaire, 0, ',', ' ') }}</td>
                                    <td class="num" style="font-weight:700">{{ number_format($detail->total, 0, ',', ' ') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div style="padding:1rem 1.25rem;border-top:1px solid var(--line);display:grid;gap:.35rem;justify-items:end">
                    <div class="muted">Sous-total : <strong style="color:var(--text)">{{ number_format($vente->sous_total, 0, ',', ' ') }} FCFA</strong></div>
                    <div class="muted">Remise : <strong style="color:var(--text)">− {{ number_format($vente->remise, 0, ',', ' ') }} FCFA</strong></div>
                    <div style="font-size:1.4rem;font-weight:800">{{ number_format($vente->total, 0, ',', ' ') }} <span style="font-size:.9rem">FCFA</span></div>
                </div>
            </div>

            <div class="card">
                <div class="card-pad" style="padding-bottom:.75rem">
                    <h3 class="card-title" style="margin:0">Paiement</h3>
                </div>

                <div style="overflow-x:auto">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Mode</th>
                                <th>Référence</th>
                                <th class="num">Montant</th>
                                <th>Statut</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($vente->paiements as $paiement)
                                <tr>
                                    <td>{{ $paiement->date_paiement->format('d/m/Y H:i') }}</td>
                                    <td>
                                        <span class="badge {{ $paiement->mode === 'orange_money' ? 'badge-yellow' : 'badge-gray' }}">
                                            {{ $modes[$paiement->mode] ?? $paiement->mode }}
                                        </span>
                                    </td>
                                    <td>{{ $paiement->reference_transaction ?: '—' }}</td>
                                    <td class="num" style="font-weight:700">{{ number_format($paiement->montant, 0, ',', ' ') }}</td>
                                    <td>
                                        <span class="badge {{ $paiement->statut === 'valide' ? 'badge-green' : 'badge-red' }}">
                                            {{ $paiement->statut === 'valide' ? 'Valide' : 'Annulé' }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="empty">Aucun paiement.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- Fenêtre : annuler la vente --}}
    @if ($validee)
        <x-modal name="annuler-vente" maxWidth="md" :show="$annulationActive && $errors->any()" focusable>
            <form method="POST" action="{{ route('ventes.annuler', $vente) }}" class="p-6">
                @csrf
                <input type="hidden" name="_modal" value="annuler-vente">

                <div class="confirm-icon"><x-icon name="warning" /></div>
                <h2 class="modal-title" style="text-align:center;margin-bottom:.5rem">Annuler la vente {{ $vente->numero }} ?</h2>
                <p class="muted" style="font-size:.9rem;text-align:center">
                    Les articles redeviennent disponibles, le stock est remis, et le remboursement est enregistré en caisse.
                </p>

                <div style="margin-top:1rem">
                    <x-input-label value="Motif de l'annulation *" />
                    <x-text-input name="motif" type="text" class="mt-1 block w-full" :value="$annulationActive ? old('motif') : ''" required />
                    <x-input-error :messages="$annulationActive ? $errors->get('motif') : []" class="mt-2" />
                </div>

                <div class="mt-6 flex justify-center gap-3">
                    <x-secondary-button x-on:click="$dispatch('close')">Retour</x-secondary-button>
                    <button type="submit" class="btn btn-danger">Annuler la vente</button>
                </div>
            </form>
        </x-modal>
    @endif
</x-app-layout>