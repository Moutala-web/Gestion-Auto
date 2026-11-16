@php
    $libelles = \App\Models\MouvementCaisse::LIBELLES;
    $modes = ['especes' => 'Espèces', 'orange_money' => 'Orange Money'];
    $fmt = fn ($n) => number_format($n, 0, ',', ' ');
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="page-head">
            <div>
                <h1 class="page-title">Caisse</h1>
                <p class="page-sub">Journal de toutes les entrées et sorties d'argent</p>
            </div>
            <div class="page-actions">
                <a href="{{ route('caisse.export', request()->query()) }}" class="btn btn-ghost" title="Télécharger le journal affiché, au format Excel (CSV)">
                    <x-icon name="download" /> Exporter
                </a>
                <a href="{{ route('depenses.index') }}" class="btn btn-primary"><x-icon name="plus" /> Nouvelle dépense</a>
            </div>
        </div>
    </x-slot>

    @include('partials.messages')

    {{-- Soldes réels (sur tout l'historique) --}}
    <div class="stat-grid">
        <div class="card stat-card">
            <span class="stat-icon stat-amber"><x-icon name="banknotes" /></span>
            <div>
                <div class="stat-value {{ $soldes['total'] < 0 ? 'text-red-600' : '' }}">{{ $fmt($soldes['total']) }}</div>
                <div class="stat-label">Solde total (FCFA)</div>
            </div>
        </div>
        <div class="card stat-card">
            <span class="stat-icon stat-teal"><x-icon name="banknotes" /></span>
            <div>
                <div class="stat-value {{ $soldes['especes'] < 0 ? 'text-red-600' : '' }}">{{ $fmt($soldes['especes']) }}</div>
                <div class="stat-label">En espèces</div>
            </div>
        </div>
        <div class="card stat-card">
            <span class="stat-icon stat-indigo"><x-icon name="banknotes" /></span>
            <div>
                <div class="stat-value {{ $soldes['orange_money'] < 0 ? 'text-red-600' : '' }}">{{ $fmt($soldes['orange_money']) }}</div>
                <div class="stat-label">Orange Money</div>
            </div>
        </div>
    </div>

    {{-- Filtres --}}
    <form method="GET" action="{{ route('caisse.index') }}" class="toolbar">
        <div>
            <label class="muted" style="font-size:.72rem;font-weight:700;display:block">Du</label>
            <input type="date" name="du" value="{{ $du }}">
        </div>
        <div>
            <label class="muted" style="font-size:.72rem;font-weight:700;display:block">Au</label>
            <input type="date" name="au" value="{{ $au }}">
        </div>
        <div>
            <label class="muted" style="font-size:.72rem;font-weight:700;display:block">Sens</label>
            <select name="type">
                <option value="">Tous</option>
                <option value="entree" @selected($type === 'entree')>Entrées</option>
                <option value="sortie" @selected($type === 'sortie')>Sorties</option>
            </select>
        </div>
        <div>
            <label class="muted" style="font-size:.72rem;font-weight:700;display:block">Nature</label>
            <select name="categorie">
                <option value="">Toutes</option>
                @foreach ($libelles as $cle => $libelle)
                    <option value="{{ $cle }}" @selected($categorie === $cle)>{{ $libelle }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="muted" style="font-size:.72rem;font-weight:700;display:block">Mode</label>
            <select name="mode">
                <option value="">Tous</option>
                @foreach ($modes as $cle => $libelle)
                    <option value="{{ $cle }}" @selected($mode === $cle)>{{ $libelle }}</option>
                @endforeach
            </select>
        </div>
        <div style="align-self:flex-end;display:flex;gap:.5rem">
            <button type="submit" class="btn btn-ghost">Filtrer</button>
            <a href="{{ route('caisse.index') }}" class="btn btn-ghost">Mois en cours</a>
        </div>
    </form>

    {{-- Chiffres de la période choisie --}}
    <div class="stat-grid">
        <div class="card stat-card">
            <span class="stat-icon stat-teal"><x-icon name="check-circle" /></span>
            <div>
                <div class="stat-value">{{ $fmt($periode['entrees']) }}</div>
                <div class="stat-label">Entrées de la période</div>
            </div>
        </div>
        <div class="card stat-card">
            <span class="stat-icon stat-rose"><x-icon name="trend-down" /></span>
            <div>
                <div class="stat-value">{{ $fmt($periode['sorties']) }}</div>
                <div class="stat-label">Sorties de la période</div>
            </div>
        </div>
        <div class="card stat-card">
            <span class="stat-icon stat-sky"><x-icon name="cart" /></span>
            <div>
                <div class="stat-value">{{ $fmt($periode['ca']) }}</div>
                <div class="stat-label">Chiffre d'affaires (hors caution)</div>
            </div>
        </div>
    </div>

    <div class="card" style="overflow-x:auto">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Opération</th>
                    <th>Mode</th>
                    <th class="num">Montant</th>
                    <th>Par</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($mouvements as $mouvement)
                    @php
                        $entree = $mouvement->type === 'entree';
                        $lien = match ($mouvement->reference_type) {
                            'vente' => route('ventes.show', $mouvement->reference_id),
                            'location' => route('locations.show', $mouvement->reference_id),
                            'depense' => route('depenses.index'),
                            default => null,
                        };
                    @endphp
                    <tr>
                        <td>
                            <div>{{ $mouvement->date_operation->format('d/m/Y') }}</div>
                            <div class="person-sub">{{ $mouvement->date_operation->format('H:i') }}</div>
                        </td>
                        <td>
                            <span class="badge {{ $entree ? 'badge-green' : 'badge-red' }}">{{ $libelles[$mouvement->categorie] ?? $mouvement->categorie }}</span>
                            <div class="person-sub" style="margin-top:.25rem">
                                @if ($lien)
                                    <a href="{{ $lien }}" class="link">{{ $mouvement->description ?: 'Voir' }}</a>
                                @else
                                    {{ $mouvement->description ?: '—' }}
                                @endif
                            </div>
                        </td>
                        <td>
                            <span class="badge {{ $mouvement->mode_paiement === 'orange_money' ? 'badge-yellow' : 'badge-gray' }}">
                                {{ $modes[$mouvement->mode_paiement] ?? $mouvement->mode_paiement }}
                            </span>
                        </td>
                        <td class="num" style="font-weight:800">
                            <span class="{{ $entree ? 'text-green-700' : 'text-red-600' }}">{{ $entree ? '+' : '−' }} {{ $fmt($mouvement->montant) }}</span>
                        </td>
                        <td>{{ $mouvement->user?->name ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="empty">Aucun mouvement sur cette période.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <p class="muted" style="font-size:.78rem;margin-top:.6rem">
        Montants en FCFA. Le journal ne se modifie jamais : une erreur est corrigée par une opération inverse.
        La caution est de l'argent détenu pour le client : elle apparaît dans le solde mais jamais dans le chiffre d'affaires.
    </p>

    <div class="mt-4">{{ $mouvements->links() }}</div>
</x-app-layout>