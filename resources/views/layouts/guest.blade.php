@php
    $image = file_exists(public_path('images/connexion.jpg')) ? asset('images/connexion.jpg') : null;

    $parametre = \App\Models\Parametre::first();
    $nom = $parametre?->nom_entreprise ?: config('app.name');
    $logo = ($parametre?->logo && is_file(public_path($parametre->logo))) ? asset($parametre->logo) : null;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $nom }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <link rel="stylesheet" href="{{ asset('vendor/fontawesome/css/all.min.css') }}">
        <link rel="stylesheet" href="{{ asset('css/theme.css') }}?v={{ filemtime(public_path('css/theme.css')) }}">
        <link rel="stylesheet" href="{{ asset('css/connexion.css') }}?v={{ filemtime(public_path('css/connexion.css')) }}">
    </head>
    <body class="login-body" @if ($image) style="--login-image: url('{{ $image }}')" @endif>
        <div class="login-page">
            <div class="login-grid">
                {{-- Texte à gauche, sur l'image --}}
                <section class="login-hero">
                    <div class="login-brand">
                        <span class="brand-mark">
                            @if ($logo)
                                <img src="{{ $logo }}" alt="">
                            @else
                                <x-icon name="truck" />
                            @endif
                        </span>
                        <div>
                            <div class="login-brand-name">CMC Group</div>
                            <div class="login-brand-tag">Le partenaire de vos ambitions</div>
                        </div>
                    </div>

                    <h2 class="login-headline">Des États-Unis au Burkina Faso, votre activité sous contrôle.</h2>

                    <ul class="login-points">
                        <li><x-icon name="check-circle" /> Coût réel et marge de chaque véhicule importé</li>
                        <li><x-icon name="check-circle" /> Caisse en espèces et en Orange Money</li>
                        <li><x-icon name="check-circle" /> Reçus PDF à l'en-tête de l'entreprise</li>
                    </ul>
                </section>

                {{-- Carte flottante avec le formulaire --}}
                <main class="login-card auth-card">
                    <div class="login-card-body">
                        <div class="login-card-brand">
                            <span class="brand-mark">
                                @if ($logo)
                                    <img src="{{ $logo }}" alt="">
                                @else
                                    <x-icon name="truck" />
                                @endif
                            </span>
                            <div>
                                <div class="login-card-name">CMC Group</div>
                                <div class="login-card-tag">Espace de gestion</div>
                            </div>
                        </div>

                        {{ $slot }}
                    </div>
                </main>
            </div>

            <footer class="login-foot">CMC Group · Une seule vision : votre satisfaction</footer>
        </div>
    </body>
</html>