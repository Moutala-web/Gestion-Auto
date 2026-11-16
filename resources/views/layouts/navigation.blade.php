@php
    $parametre = \App\Models\Parametre::first();
    $nomEntreprise = $parametre?->nom_entreprise ?: config('app.name');
    $logo = ($parametre?->logo && is_file(public_path($parametre->logo))) ? asset($parametre->logo) : null;

    // Pièces et articles divers partagent les mêmes pages : on regarde la famille pour savoir quel lien est actif
    $pieceCourante = request()->route('piece');
    $familleCourante = request('famille') ?: (is_object($pieceCourante) ? $pieceCourante->famille : null);
    $surPieces = request()->routeIs('pieces.*');

    $lien = fn (string $route, bool $actif, string $icone, string $libelle, array $params = [])
        => compact('route', 'actif', 'icone', 'libelle', 'params');

    $sections = [
        'Principal' => [
            $lien('dashboard', request()->routeIs('dashboard'), 'home', 'Tableau de bord'),
        ],
        'Activité' => [
            $lien('ventes.index', request()->routeIs('ventes.*'), 'cart', 'Ventes'),
            $lien('services.index', request()->routeIs('services.*'), 'handshake', 'Prestations'),
            $lien('locations.index', request()->routeIs('locations.*'), 'key', 'Locations'),
        ],
        'Stock' => [
            $lien('modeles.index', request()->routeIs('modeles.*'), 'tags', 'Catalogue'),
            $lien('vehicules.index', request()->routeIs('vehicules.*'), 'truck', 'Véhicules'),
            $lien('motos.index', request()->routeIs('motos.*'), 'bolt', 'Motos'),
            $lien('pieces.index', $surPieces && $familleCourante !== 'divers', 'cube', 'Pièces', ['famille' => 'piece']),
            $lien('pieces.index', $surPieces && $familleCourante === 'divers', 'couch', 'Articles divers', ['famille' => 'divers']),
        ],
        'Finances' => [
            $lien('caisse.index', request()->routeIs('caisse.*'), 'cash-register', 'Caisse'),
            $lien('achats.index', request()->routeIs('achats.*'), 'inbox-in', 'Achats'),
            $lien('depenses.index', request()->routeIs('depenses.*'), 'receipt', 'Dépenses'),
        ],
        'Relations' => [
            $lien('clients.index', request()->routeIs('clients.*'), 'users', 'Clients'),
        ],
        'Administration' => [
            $lien('parametres.edit', request()->routeIs('parametres.*'), 'cog', 'Paramètres'),
            $lien('sauvegardes.index', request()->routeIs('sauvegardes.*'), 'database', 'Sauvegardes'),
        ],
    ];
@endphp

<aside class="sidebar" :class="{ 'is-open': sidebar }">
    <div class="sidebar-brand">
        <span class="brand-mark">
            @if ($logo)
                <img src="{{ $logo }}" alt="">
            @else
                <x-icon name="truck" />
            @endif
        </span>
        <div>
            <div class="brand-name">CMC Group</div>
            <div class="brand-tag">Le partenaire de vos ambitions</div>
        </div>
    </div>

    <nav class="sidebar-nav">
        @foreach ($sections as $section => $liens)
            <p class="sidebar-section">{{ $section }}</p>

            @foreach ($liens as $l)
                <a href="{{ route($l['route'], $l['params']) }}" class="sidebar-link {{ $l['actif'] ? 'active' : '' }}">
                    <x-icon :name="$l['icone']" />
                    {{ $l['libelle'] }}
                </a>
            @endforeach
        @endforeach
    </nav>

    <div class="sidebar-user">
        <span class="avatar">{{ mb_strtoupper(mb_substr(Auth::user()->name, 0, 1)) }}</span>
        <div class="sidebar-user-text">
            <div class="sidebar-user-name">{{ Auth::user()->name }}</div>
            <div class="sidebar-user-mail">{{ Auth::user()->email }}</div>
        </div>
        <div class="sidebar-user-actions">
            <a href="{{ route('profile.edit') }}" class="sidebar-icon-btn" title="Mon profil"><x-icon name="user" /></a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="sidebar-icon-btn" title="Déconnexion"><x-icon name="logout" /></button>
            </form>
        </div>
    </div>
</aside>

<div class="sidebar-backdrop" x-show="sidebar" x-transition.opacity x-on:click="sidebar = false"></div>