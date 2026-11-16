@csrf
<input type="hidden" name="_modal" value="{{ $modal }}">

@php
    $c = $client ?? null;
    // Les valeurs saisies et les erreurs ne concernent que la fenêtre qui vient d'échouer
    $actif = old('_modal') === $modal;
    $val = fn ($champ) => $actif ? old($champ, $c?->{$champ}) : $c?->{$champ};
    $err = fn ($champ) => $actif ? $errors->get($champ) : [];
@endphp

<div class="grid grid-cols-1 gap-4 md:grid-cols-2">
    <div class="form-section md:col-span-2">Identité</div>

    <div>
        <x-input-label value="Nom *" />
        <x-text-input name="nom" type="text" class="mt-1 block w-full" :value="$val('nom')" required />
        <x-input-error :messages="$err('nom')" class="mt-2" />
    </div>

    <div>
        <x-input-label value="Prénom" />
        <x-text-input name="prenom" type="text" class="mt-1 block w-full" :value="$val('prenom')" />
        <x-input-error :messages="$err('prenom')" class="mt-2" />
    </div>

    <div class="md:col-span-2">
        <x-input-label value="Type *" />
        <select name="type" class="mt-1 block w-full">
            <option value="client" @selected(($val('type') ?? 'client') === 'client')>Client</option>
            <option value="prospect" @selected($val('type') === 'prospect')>Prospect</option>
        </select>
        <x-input-error :messages="$err('type')" class="mt-2" />
    </div>

    <div class="form-section md:col-span-2">Coordonnées</div>

    <div>
        <x-input-label value="Téléphone *" />
        <x-text-input name="telephone" type="text" class="mt-1 block w-full" :value="$val('telephone')" required />
        <x-input-error :messages="$err('telephone')" class="mt-2" />
    </div>

    <div>
        <x-input-label value="Email" />
        <x-text-input name="email" type="email" class="mt-1 block w-full" :value="$val('email')" />
        <x-input-error :messages="$err('email')" class="mt-2" />
    </div>

    <div class="md:col-span-2">
        <x-input-label value="Adresse" />
        <textarea name="adresse" rows="2" class="mt-1 block w-full">{{ $val('adresse') }}</textarea>
        <x-input-error :messages="$err('adresse')" class="mt-2" />
    </div>

    <div class="form-section md:col-span-2">Informations complémentaires</div>

    <div class="md:col-span-2">
        <x-input-label value="Notes" />
        <textarea name="notes" rows="3" class="mt-1 block w-full">{{ $val('notes') }}</textarea>
        <x-input-error :messages="$err('notes')" class="mt-2" />
    </div>
</div>