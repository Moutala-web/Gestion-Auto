<x-app-layout>
    <x-slot name="header">
        <div class="page-head">
            <div>
                <h1 class="page-title">Paramètres</h1>
                <p class="page-sub">Ces informations apparaissent sur les reçus PDF</p>
            </div>
        </div>
    </x-slot>

    @include('partials.messages')

    <form method="POST" action="{{ route('parametres.update') }}" enctype="multipart/form-data" class="card card-pad" style="max-width:48rem">
        @csrf
        @method('PUT')

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <x-input-label value="Nom de l'entreprise *" />
                <x-text-input name="nom_entreprise" type="text" class="mt-1 block w-full" :value="old('nom_entreprise', $parametre->nom_entreprise)" required />
                <x-input-error :messages="$errors->get('nom_entreprise')" class="mt-2" />
            </div>

            <div>
                <x-input-label value="Téléphone" />
                <x-text-input name="telephone" type="text" class="mt-1 block w-full" :value="old('telephone', $parametre->telephone)" />
                <x-input-error :messages="$errors->get('telephone')" class="mt-2" />
            </div>

            <div>
                <x-input-label value="Email" />
                <x-text-input name="email" type="email" class="mt-1 block w-full" :value="old('email', $parametre->email)" />
                <x-input-error :messages="$errors->get('email')" class="mt-2" />
            </div>

            <div class="sm:col-span-2">
                <x-input-label value="Adresse" />
                <textarea name="adresse" rows="2" class="mt-1 block w-full">{{ old('adresse', $parametre->adresse) }}</textarea>
                <x-input-error :messages="$errors->get('adresse')" class="mt-2" />
            </div>

            <div>
                <x-input-label value="Devise affichée *" />
                <x-text-input name="devise" type="text" class="mt-1 block w-full" :value="old('devise', $parametre->devise ?: 'FCFA')" required />
                <x-input-error :messages="$errors->get('devise')" class="mt-2" />
            </div>

            <div class="sm:col-span-2">
                <x-input-label value="Logo (JPG ou PNG, 2 Mo maximum)" />

                @if ($parametre->logo)
                    <div style="margin:.6rem 0;display:flex;align-items:center;gap:1rem">
                        <img src="{{ asset($parametre->logo) }}" alt="Logo actuel"
                             style="max-height:4rem;max-width:12rem;border:1px solid var(--line);border-radius:.6rem;padding:.4rem;background:#fff">
                        <label style="display:flex;align-items:center;gap:.5rem;font-size:.85rem;font-weight:600">
                            <input type="checkbox" name="supprimer_logo" value="1"> Supprimer le logo
                        </label>
                    </div>
                @endif

                <input type="file" name="logo" accept=".jpg,.jpeg,.png" class="mt-1 block w-full">
                <x-input-error :messages="$errors->get('logo')" class="mt-2" />
            </div>
        </div>

        <div class="mt-6 flex justify-end">
            <x-primary-button>Enregistrer</x-primary-button>
        </div>
    </form>
</x-app-layout>