@csrf
<input type="hidden" name="_modal" value="{{ $modal }}">

@php
    $v = $vehicule;
    $statutVerrouille = in_array($v->statut, ['en_location', 'vendu'], true);
    // Les valeurs saisies et les erreurs ne concernent que la fenêtre qui vient d'échouer
    $actif = old('_modal') === $modal;
    $val = fn ($champ) => $actif ? old($champ, $v->{$champ}) : $v->{$champ};
    $err = fn ($champ) => $actif ? $errors->get($champ) : [];
    $liste = $modeles ?? collect();
@endphp

<div class="grid grid-cols-1 gap-4 md:grid-cols-2">
    <div class="form-section md:col-span-2">Fixé par l'achat</div>

    <div class="md:col-span-2" style="border:1px solid var(--line);background:#f7f9fd;border-radius:.5rem;padding:.75rem 1rem;display:flex;gap:2.5rem;flex-wrap:wrap;font-size:.85rem">
        <div><span class="muted">Référence</span><br><strong>{{ $v->reference }}</strong></div>
        <div><span class="muted">Prix d'achat</span><br><strong>{{ number_format($v->prix_achat, 0, ',', ' ') }} FCFA</strong></div>
        <div class="muted" style="flex-basis:100%;font-size:.75rem">Ces deux valeurs viennent de l'achat et ne se modifient pas ici.</div>
    </div>

    <div class="form-section md:col-span-2">Identification</div>

    <div class="md:col-span-2">
        <x-input-label value="Modèle *" />
        <select name="modele_id" class="mt-1 block w-full" required>
            @foreach ($liste as $m)
                <option value="{{ $m->id }}" @selected((int) $val('modele_id') === $m->id)>
                    {{ $m->marque }} {{ $m->modele }}{{ $m->categorie ? ' · ' . $m->categorie : '' }}
                </option>
            @endforeach
        </select>
        <p class="muted" style="font-size:.75rem;margin-top:.3rem">
            Modèle absent de la liste ? <a href="{{ route('modeles.index', ['type' => 'voiture']) }}" class="link">Ajoute-le au catalogue</a>.
        </p>
        <x-input-error :messages="$err('modele_id')" class="mt-2" />
    </div>

    <div>
        <x-input-label value="Immatriculation" />
        <x-text-input name="immatriculation" type="text" class="mt-1 block w-full" :value="$val('immatriculation')" />
        <x-input-error :messages="$err('immatriculation')" class="mt-2" />
    </div>

    <div>
        <x-input-label value="Numéro de châssis (VIN)" />
        <x-text-input name="numero_chassis" type="text" maxlength="50" class="mt-1 block w-full" :value="$val('numero_chassis')" />
        <x-input-error :messages="$err('numero_chassis')" class="mt-2" />
    </div>

    <div>
        <x-input-label value="Année" />
        <x-text-input name="annee" type="number" class="mt-1 block w-full" :value="$val('annee')" />
        <x-input-error :messages="$err('annee')" class="mt-2" />
    </div>

    <div>
        <x-input-label value="Couleur" />
        <x-text-input name="couleur" type="text" class="mt-1 block w-full" :value="$val('couleur')" />
        <x-input-error :messages="$err('couleur')" class="mt-2" />
    </div>

    <div class="form-section md:col-span-2">État et statut</div>

    <div>
        <x-input-label value="Kilométrage" />
        <x-text-input name="kilometrage" type="number" min="0" class="mt-1 block w-full" :value="$val('kilometrage') ?? 0" />
        <x-input-error :messages="$err('kilometrage')" class="mt-2" />
    </div>

    <div>
        <x-input-label value="Statut" />
        @if ($statutVerrouille)
            <div class="mt-2">@include('vehicules._statut', ['statut' => $v->statut])</div>
            <p class="muted" style="font-size:.75rem;margin-top:.3rem">Ce statut est géré automatiquement par les ventes et les locations.</p>
        @else
            <select name="statut" class="mt-1 block w-full">
                <option value="disponible" @selected(($val('statut') ?? 'disponible') === 'disponible')>Disponible</option>
                <option value="reserve" @selected($val('statut') === 'reserve')>Réservé</option>
                <option value="maintenance" @selected($val('statut') === 'maintenance')>Maintenance</option>
            </select>
        @endif
        <x-input-error :messages="$err('statut')" class="mt-2" />
    </div>

    <div class="form-section md:col-span-2">Prix de vente</div>

    <div class="md:col-span-2">
        <x-input-label value="Prix de vente (FCFA)" />
        <x-text-input name="prix_vente" type="number" min="0" step="1" class="mt-1 block w-full" :value="$val('prix_vente')" />
        <x-input-error :messages="$err('prix_vente')" class="mt-2" />
    </div>

    <div class="form-section md:col-span-2">Description</div>

    <div class="md:col-span-2">
        <x-input-label value="Description" />
        <textarea name="description" rows="3" class="mt-1 block w-full">{{ $val('description') }}</textarea>
        <x-input-error :messages="$err('description')" class="mt-2" />
    </div>
</div>