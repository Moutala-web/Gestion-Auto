@php
    $fmt = fn ($n) => number_format((float) $n, 0, ',', ' ');

    // Montants compacts : 5,9 M, 850 k
    $compact = function ($n) {
        $n = (float) $n;

        if (abs($n) >= 1000000) {
            return number_format($n / 1000000, 1, ',', ' ') . ' M';
        }

        if (abs($n) >= 1000) {
            return number_format($n / 1000, 0, ',', ' ') . ' k';
        }

        return number_format($n, 0, ',', ' ');
    };

    $libellesCaisse = \App\Models\MouvementCaisse::LIBELLES;
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="page-head">
            <div>
                <h1 class="page-title">Tableau de bord</h1>
                <p class="page-sub">{{ now()->translatedFormat('l j F Y') }}</p>
            </div>
        </div>
    </x-slot>

    @include('partials.messages')

    <section class="hero">
        <p class="hero-hi">Bonjour, {{ \Illuminate\Support\Str::before(Auth::user()->name, ' ') }}</p>
        <h2 class="hero-title">Voici l'état de votre activité.</h2>
        <div class="hero-links">
            <a href="{{ route('ventes.index') }}" class="btn btn-primary"><x-icon name="cart" /> Ventes</a>
            <a href="{{ route('locations.index') }}" class="btn btn-ghost"><x-icon name="key" /> Locations</a>
            <a href="{{ route('achats.index') }}" class="btn btn-ghost"><x-icon name="inbox-in" /> Achats</a>
            <a href="{{ route('caisse.index') }}" class="btn btn-ghost"><x-icon name="cash-register" /> Caisse</a>
        </div>
    </section>

    {{-- Argent --}}
    <div class="stat-grid">
        <div class="card stat-card">
            <span class="stat-icon stat-amber"><x-icon name="cash-register" /></span>
            <div>
                <div class="stat-value {{ $soldes['total'] < 0 ? 'text-red-600' : '' }}">{{ $fmt($soldes['total']) }}</div>
                <div class="stat-label">Solde de caisse (FCFA)</div>
                <div class="muted" style="font-size:.72rem;margin-top:.2rem">
                    Espèces {{ $fmt($soldes['especes']) }} · Orange Money {{ $fmt($soldes['orange_money']) }}
                </div>
            </div>
        </div>

        <div class="card stat-card">
            <span class="stat-icon stat-teal"><x-icon name="cart" /></span>
            <div>
                <div class="stat-value">{{ $fmt($caMois) }}</div>
                <div class="stat-label">Chiffre d'affaires du mois</div>
                <div style="font-size:.72rem;margin-top:.2rem;font-weight:700">
                    @if ($variation === null)
                        <span class="muted">Pas de comparaison possible</span>
                    @else
                        <span class="{{ $variation >= 0 ? 'text-green-700' : 'text-red-600' }}">
                            {{ $variation >= 0 ? '+' : '' }}{{ $variation }} %
                        </span>
                        <span class="muted">vs mois dernier</span>
                    @endif
                </div>
            </div>
        </div>

        <div class="card stat-card">
            <span class="stat-icon stat-rose"><x-icon name="receipt" /></span>
            <div>
                <div class="stat-value">{{ $fmt($depenses['total']) }}</div>
                <div class="stat-label">Dépenses du mois</div>
                <div class="muted" style="font-size:.72rem;margin-top:.2rem">
                    Achats {{ $compact($depenses['achat']) }} · Frais {{ $compact($depenses['frais_vehicule']) }} · Fonct. {{ $compact($depenses['depense']) }}
                </div>
                <a href="{{ route('depenses.index') }}" class="link" style="font-size:.72rem">Voir le détail</a>
            </div>
        </div>

        <div class="card stat-card">
            <span class="stat-icon stat-sky"><x-icon name="inbox-in" /></span>
            <div>
                <div class="stat-value">{{ $fmt($valeurStock) }}</div>
                <div class="stat-label">Valeur du stock</div>
                <div class="muted" style="font-size:.72rem;margin-top:.2rem">Au prix d'achat</div>
            </div>
        </div>
    </div>

    {{-- Activité --}}
    <div class="stat-grid">
        <div class="card stat-card">
            <span class="stat-icon stat-amber"><x-icon name="truck" /></span>
            <div><div class="stat-value">{{ $stats['vehicules'] }}</div><div class="stat-label">Véhicules disponibles</div></div>
        </div>
        <div class="card stat-card">
            <span class="stat-icon stat-indigo"><x-icon name="bolt" /></span>
            <div><div class="stat-value">{{ $stats['motos'] }}</div><div class="stat-label">Motos disponibles</div></div>
        </div>
        <div class="card stat-card">
            <span class="stat-icon stat-sky"><x-icon name="key" /></span>
            <div>
                <div class="stat-value">{{ $stats['locations_en_cours'] }}</div>
                <div class="stat-label">Locations en cours</div>
                @if ($stats['locations_en_retard'] > 0)
                    <div class="text-red-600" style="font-size:.72rem;margin-top:.2rem;font-weight:700">{{ $stats['locations_en_retard'] }} en retard</div>
                @endif
            </div>
        </div>
        <div class="card stat-card">
            <span class="stat-icon stat-rose"><x-icon name="cube" /></span>
            <div><div class="stat-value">{{ $stats['pieces_alerte'] }}</div><div class="stat-label">Pièces en stock bas</div></div>
        </div>
        <div class="card stat-card">
            <span class="stat-icon stat-teal"><x-icon name="users" /></span>
            <div><div class="stat-value">{{ $stats['clients'] }}</div><div class="stat-label">Clients et prospects</div></div>
        </div>
    </div>

    <div class="grid-eq">
        {{-- Graphique --}}
        <div class="card card-pad">
            <h3 class="card-title">Chiffre d'affaires sur 6 mois <span class="muted" style="font-weight:500">(FCFA)</span></h3>

            <div style="display:flex;gap:.6rem;align-items:stretch">
                @foreach ($mois as $m)
                    @php
                        $hauteur = $m['ca'] > 0 ? max((int) round($m['ca'] / $maxCa * 100), 4) : 0;
                    @endphp
                    <div style="flex:1;text-align:center;min-width:0">
                        <div style="height:9rem;display:flex;align-items:flex-end;justify-content:center;border-bottom:2px solid var(--line)">
                            @if ($hauteur > 0)
                                <div title="{{ $fmt($m['ca']) }} FCFA"
                                     style="width:100%;max-width:3.2rem;height:{{ $hauteur }}%;background:{{ $m['courant'] ? 'var(--brand-600)' : '#b9c9f2' }}"></div>
                            @endif
                        </div>
                        <div style="font-size:.72rem;font-weight:700;margin-top:.45rem;text-transform:capitalize">{{ $m['label'] }}</div>
                        <div class="muted" style="font-size:.68rem">{{ $m['ca'] != 0 ? $compact($m['ca']) : '—' }}</div>
                    </div>
                @endforeach
            </div>

            <p class="muted" style="font-size:.74rem;margin:.9rem 0 0">Ventes et locations encaissées, hors caution, moins les annulations.</p>
        </div>

        {{-- Locations à suivre --}}
        <div class="card card-pad">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.9rem">
                <h3 class="card-title" style="margin:0">Locations à suivre</h3>
                <a href="{{ route('locations.index') }}" class="link" style="font-size:.8rem">Tout voir</a>
            </div>

            @forelse ($locations as $location)
                @php($enRetard = $location->statut === 'en_cours' && now()->greaterThan($location->date_retour_prevue))
                <div class="list-row">
                    <div>
                        <a href="{{ route('locations.show', $location) }}" class="list-main link">{{ $location->numero }} · {{ $location->client->nom_complet }}</a>
                        <div class="list-sub">
                            {{ $location->vehicule->marque }} {{ $location->vehicule->modele }}
                            · retour {{ $location->date_retour_prevue->format('d/m H:i') }}
                        </div>
                    </div>
                    @if ($enRetard)
                        <span class="badge badge-red">En retard</span>
                    @elseif ($location->statut === 'en_cours')
                        <span class="badge badge-blue">En cours</span>
                    @else
                        <span class="badge badge-yellow">Réservée</span>
                    @endif
                </div>
            @empty
                <p class="muted" style="font-size:.9rem">Aucune location en cours ou réservée.</p>
            @endforelse
        </div>

        {{-- Dernières ventes --}}
        <div class="card card-pad">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.9rem">
                <h3 class="card-title" style="margin:0">Dernières ventes</h3>
                <a href="{{ route('ventes.index') }}" class="link" style="font-size:.8rem">Tout voir</a>
            </div>

            @forelse ($ventes as $vente)
                <div class="list-row">
                    <div>
                        <a href="{{ route('ventes.show', $vente) }}" class="list-main link">{{ $vente->numero }} · {{ $vente->client->nom_complet }}</a>
                        <div class="list-sub">{{ $vente->date_vente->format('d/m/Y H:i') }}</div>
                    </div>
                    <div style="text-align:right">
                        <div class="list-main">{{ $fmt($vente->total) }}</div>
                        @if ($vente->statut === 'annulee')
                            <span class="badge badge-red">Annulée</span>
                        @endif
                    </div>
                </div>
            @empty
                <p class="muted" style="font-size:.9rem">Aucune vente pour l'instant.</p>
            @endforelse
        </div>

        {{-- Dernières opérations de caisse --}}
        <div class="card card-pad">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.9rem">
                <h3 class="card-title" style="margin:0">Dernières opérations de caisse</h3>
                <a href="{{ route('caisse.index') }}" class="link" style="font-size:.8rem">Tout voir</a>
            </div>

            @forelse ($mouvements as $mouvement)
                @php($entree = $mouvement->type === 'entree')
                <div class="list-row">
                    <div>
                        <div class="list-main">{{ $libellesCaisse[$mouvement->categorie] ?? $mouvement->categorie }}</div>
                        <div class="list-sub">{{ \Illuminate\Support\Str::limit($mouvement->description ?: '—', 38) }} · {{ $mouvement->date_operation->format('d/m H:i') }}</div>
                    </div>
                    <div class="list-main {{ $entree ? 'text-green-700' : 'text-red-600' }}" style="white-space:nowrap">
                        {{ $entree ? '+' : '−' }} {{ $fmt($mouvement->montant) }}
                    </div>
                </div>
            @empty
                <p class="muted" style="font-size:.9rem">Aucune opération en caisse.</p>
            @endforelse
        </div>

        {{-- Pièces à réapprovisionner --}}
        <div class="card card-pad">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.9rem">
                <h3 class="card-title" style="margin:0">Pièces à réapprovisionner</h3>
                <a href="{{ route('pieces.index', ['alerte' => 1]) }}" class="link" style="font-size:.8rem">Tout voir</a>
            </div>

            @forelse ($piecesEnAlerte as $piece)
                <div class="list-row">
                    <div>
                        <a href="{{ route('pieces.show', $piece) }}" class="list-main link">{{ $piece->designation }}</a>
                        <div class="list-sub">{{ $piece->reference }} · seuil {{ $piece->seuil_alerte }}</div>
                    </div>
                    <span class="badge badge-red">{{ $piece->quantite_stock }} en stock</span>
                </div>
            @empty
                <p class="muted" style="font-size:.9rem">Tout est en ordre : aucun stock bas.</p>
            @endforelse
        </div>
    </div>
</x-app-layout>