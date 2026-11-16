<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'CMC Group') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <link rel="stylesheet" href="{{ asset('vendor/fontawesome/css/all.min.css') }}">
        <link rel="stylesheet" href="{{ asset('css/theme.css') }}?v={{ filemtime(public_path('css/theme.css')) }}">
        <link rel="stylesheet" href="{{ asset('css/notifications.css') }}?v={{ filemtime(public_path('css/notifications.css')) }}">
    </head>
    <body class="app-body">
        <div x-data="{ sidebar: false }" class="app-shell">
            @include('layouts.navigation')

            <div class="app-main">
                {{-- Barre du haut : bouton du menu (téléphone et tablette) et cloche de notifications --}}
                <div class="appbar">
                    <div class="appbar-left">
                        <button type="button" class="appbar-menu" x-on:click="sidebar = true" aria-label="Ouvrir le menu"><x-icon name="menu" /></button>
                        <strong class="appbar-brand">{{ config('app.name') }}</strong>
                    </div>

                    @include('partials.cloche')
                </div>

                @isset($header)
                    <header class="page-header">{{ $header }}</header>
                @endisset

                <main class="app-content">
                    {{ $slot }}
                </main>
            </div>
        </div>
    </body>
</html>