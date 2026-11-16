<x-app-layout>
    <x-slot name="header">
        <div class="page-head">
            <div>
                <h1 class="page-title">Services divers</h1>
                <p class="page-sub">{{ $nombre }} prestation(s) · total <strong>{{ number_format($total, 0, ',', ' ') }} FCFA</strong> sur la période</p>
            </div>
            <div class="page-actions">
                <a href="{{ route('ventes.index', ['nouveau' => 'service']) }}" class="btn btn-primary">
                    <x-icon name="plus" /> Nouveau service
                </a>
            </div>
        </div>
    </x-slot>

    @include('partials.messages')

    <form method="GET" action="{{ route('services.index') }}" class="toolbar">
        <div class="search">
            <x-icon name="search" />
            <input type="text" name="q" value="{{ $recherche }}" placeholder="Nom du service">
        </div>
        <input type="date" name="du" value="{{ $du }}" title="Du">
        <input type="date" name="au" value="{{ $au }}" title="Au">
        <button type="submit" class="btn btn-ghost">Filtrer</button>
        <a href="{{ route('services.index') }}" class="btn btn-ghost">Mois en cours</a>
    </form>

    <div class="grid-2">
        {{-- Répartition par service --}}
        <div class="card card-pad">
            <h3 class="card-title">Par service <span class="muted" style="font-weight:500">(FCFA)</span></h3>

            @forelse ($parService as $service)
                <div class="list-row">
                    <div>
                        <div class="list-main">{{ $service->designation }}</div>
                        <div class="list-sub">{{ (int) $service->quantite }} prestation(s)</div>
                    </div>
                    <div class="list-main">{{ number_format($service->montant, 0, ',', ' ') }}</div>
                </div>
            @empty
                <p class="muted" style="font-size:.9rem">Aucun service vendu sur cette période.</p>
            @endforelse
        </div>

        {{-- Détail --}}
        <div class="card">
            <div class="card-pad" style="padding-bottom:.75rem">
                <h3 class="card-title" style="margin:0">Détail des prestations</h3>
            </div>

            <div style="overflow-x:auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Service</th>
                            <th>Vente</th>
                            <th>Client</th>
                            <th class="num">Qté</th>
                            <th class="num">Prix unitaire</th>
                            <th class="num">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($lignes as $ligne)
                            @php($annulee = $ligne->vente->statut === 'annulee')
                            <tr style="{{ $annulee ? 'opacity:.55' : '' }}">
                                <td>
                                    <div class="person-name" style="{{ $annulee ? 'text-decoration:line-through' : '' }}">{{ $ligne->designation }}</div>
                                    @if ($annulee)
                                        <span class="badge badge-red" style="margin-top:.25rem">Vente annulée</span>
                                    @endif
                                </td>
                                <td>
                                    <a href="{{ route('ventes.show', $ligne->vente) }}" class="link">{{ $ligne->vente->numero }}</a>
                                    <div class="person-sub">{{ $ligne->vente->date_vente->format('d/m/Y') }}</div>
                                </td>
                                <td>{{ $ligne->vente->client->nom_complet }}</td>
                                <td class="num">{{ $ligne->quantite }}</td>
                                <td class="num">{{ number_format($ligne->prix_unitaire, 0, ',', ' ') }}</td>
                                <td class="num" style="font-weight:800">{{ number_format($ligne->total, 0, ',', ' ') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="empty">Aucune prestation sur cette période.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div style="padding:0 1rem 1rem">{{ $lignes->links() }}</div>
        </div>
    </div>

    <p class="muted" style="font-size:.78rem;margin-top:.6rem">
        Un service se vend comme un article : il apparaît sur la vente, le reçu PDF et la caisse. Les ventes annulées ne comptent pas dans le total.
    </p>
</x-app-layout>