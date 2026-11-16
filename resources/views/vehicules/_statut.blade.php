@php
    $styles = [
        'disponible' => ['Disponible', 'badge-green'],
        'reserve' => ['Réservé', 'badge-yellow'],
        'en_location' => ['En location', 'badge-blue'],
        'vendu' => ['Vendu', 'badge-gray'],
        'maintenance' => ['Maintenance', 'badge-red'],
    ];
    [$libelle, $classe] = $styles[$statut] ?? [$statut, 'badge-gray'];
@endphp

<span class="badge {{ $classe }}">{{ $libelle }}</span>