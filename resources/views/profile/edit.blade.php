<x-app-layout>
    <x-slot name="header">
        <div class="page-head">
            <div>
                <h1 class="page-title">Mon profil</h1>
                <p class="page-sub">Vos informations de connexion et votre mot de passe</p>
            </div>
        </div>
    </x-slot>

    <div class="stack" style="max-width:40rem">
        <div class="card card-pad">
            @include('profile.partials.update-profile-information-form')
        </div>

        <div class="card card-pad">
            @include('profile.partials.update-password-form')
        </div>
    </div>
</x-app-layout>