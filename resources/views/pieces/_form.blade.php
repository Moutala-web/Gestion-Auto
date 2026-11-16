@csrf
<input type="hidden" name="_modal" value="{{ $modal }}">

@php
    $p = $piece;
    // Les valeurs saisies et les erreurs ne concernent que la fenêtre qui vient d'échouer
    $actif = old('_modal') === $modal;
    $val = fn ($champ) => $actif ? old($champ, $p->{$champ}) : $p->{$champ};
    $err = fn ($champ) => $actif ? $errors->get($champ) : [];
@endphp

<div class="grid grid-cols-1 gap-4 md:grid-cols-2">
    <div class="form-section md:col-span-2">Fixé par le catalogue et les achats</div>

    <div class="md:col-span-2" style="border:1px solid var(--line);background:#f7f9fd;border-radius:.5rem;padding:.75rem 1rem;display:flex;gap:2rem;flex-wrap:wrap;font-size:.85rem">
        <div><span class="muted">Référence</span><br><strong>{{ $p->reference }}</strong></div>
        <div><span class="muted">Désignation</span><br><strong>{{ $p->designation }}</strong></div>
        <div><span class="muted">Stock actuel</span><br><strong>{{ $p->quantite_stock }}</strong></div>
        <div><span class="muted">Prix d'achat moyen</span><br><strong>{{ number_format($p->prix_achat, 0, ',', ' ') }} FCFA</strong></div>
        <div class="muted" style="flex-basis:100%;font-size:.75rem">
            La désignation et la catégorie se changent dans le Catalogue. Le stock évolue par les achats et les ventes, le prix d'achat moyen se recalcule à chaque achat.
        </div>
    </div>

    <div class="form-section md:col-span-2">Prix et stock</div>

    <div>
        <x-input-label value="Prix de vente (FCFA) *" />
        <x-text-input name="prix_vente" type="number" min="0" step="1" class="mt-1 block w-full" :value="$val('prix_vente')" required />
        <x-input-error :messages="$err('prix_vente')" class="mt-2" />
    </div>

    <div>
        <x-input-label value="Seuil d'alerte" />
        <x-text-input name="seuil_alerte" type="number" min="0" class="mt-1 block w-full" :value="$val('seuil_alerte') ?? 0" />
        <x-input-error :messages="$err('seuil_alerte')" class="mt-2" />
    </div>

    <div class="md:col-span-2">
        <x-input-label value="Emplacement" />
        <x-text-input name="emplacement" type="text" class="mt-1 block w-full" :value="$val('emplacement')" />
        <x-input-error :messages="$err('emplacement')" class="mt-2" />
    </div>

    <div class="form-section md:col-span-2">Description</div>

    <div class="md:col-span-2">
        <x-input-label value="Description" />
        <textarea name="description" rows="3" class="mt-1 block w-full">{{ $val('description') }}</textarea>
        <x-input-error :messages="$err('description')" class="mt-2" />
    </div>
</div>