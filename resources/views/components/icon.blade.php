@props(['name'])

@php
    // Nom utilisé dans les vues => icône Font Awesome
    $icones = [
        'home' => 'fa-gauge-high',
        'users' => 'fa-users',
        'truck' => 'fa-car-side',
        'bolt' => 'fa-motorcycle',
        'cube' => 'fa-screwdriver-wrench',
        'couch' => 'fa-couch',
        'tags' => 'fa-tags',
        'pencil' => 'fa-pen',
        'trash' => 'fa-trash-can',
        'eye' => 'fa-eye',
        'plus' => 'fa-plus',
        'search' => 'fa-magnifying-glass',
        'arrow-left' => 'fa-arrow-left',
        'logout' => 'fa-right-from-bracket',
        'menu' => 'fa-bars',
        'clock' => 'fa-clock',
        'check-circle' => 'fa-circle-check',
        'warning' => 'fa-triangle-exclamation',
        'user' => 'fa-user',
        'cart' => 'fa-cart-shopping',
        'document' => 'fa-file-lines',
        'key' => 'fa-key',
        'banknotes' => 'fa-wallet',
        'cash-register' => 'fa-cash-register',
        'receipt' => 'fa-receipt',
        'trend-down' => 'fa-arrow-trend-down',
        'inbox-in' => 'fa-boxes-stacked',
        'cog' => 'fa-gear',
        'download' => 'fa-download',
        'bell' => 'fa-bell',
        'check-double' => 'fa-check-double',
        'database' => 'fa-database',
        'handshake' => 'fa-handshake',
    ];
@endphp

<i {{ $attributes->merge(['class' => 'fa-solid ' . ($icones[$name] ?? 'fa-circle') . ' icon']) }} aria-hidden="true"></i>