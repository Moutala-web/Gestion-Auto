<x-guest-layout>
    <h1 class="login-title">Connexion</h1>
    <p class="login-sub">Entrez vos identifiants pour accéder à votre espace de gestion.</p>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="login-form">
        @csrf

        <div>
            <label for="email" class="login-label">Adresse email</label>
            <div class="login-field">
                <i class="fa-solid fa-envelope login-ico"></i>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus
                       autocomplete="username" placeholder="vous@exemple.com" class="block w-full">
            </div>
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div x-data="{ voir: false }">
            <label for="password" class="login-label">Mot de passe</label>
            <div class="login-field has-eye">
                <i class="fa-solid fa-lock login-ico"></i>
                <input id="password" type="password" x-bind:type="voir ? 'text' : 'password'" name="password" required
                       autocomplete="current-password" placeholder="Votre mot de passe" class="block w-full">
                <button type="button" class="login-eye" x-on:click="voir = ! voir"
                        x-bind:aria-label="voir ? 'Masquer le mot de passe' : 'Afficher le mot de passe'">
                    <i class="fa-solid" x-bind:class="voir ? 'fa-eye-slash' : 'fa-eye'"></i>
                </button>
            </div>
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <label for="remember_me" class="login-remember">
            <input id="remember_me" type="checkbox" name="remember">
            Se souvenir de moi
        </label>

        <button type="submit" class="btn btn-primary login-submit">
            Se connecter <i class="fa-solid fa-arrow-right"></i>
        </button>
    </form>
</x-guest-layout>