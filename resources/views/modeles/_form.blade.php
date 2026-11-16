@csrf
<input type="hidden" name="_modal" value="{{ $modal }}">

@php
    $m = $modele ?? null;
    // Les valeurs saisies et les erreurs ne concernent que la fenêtre qui vient d'échouer
    $actif = old('_modal') === $modal;
    $val = fn ($champ) => $actif ? old($champ, $m?->{$champ}) : $m?->{$champ};
    $err = fn ($champ) => $actif ? $errors->get($champ) : [];
    $typeInitial = $val('type') ?? ($typeParDefaut ?? 'voiture');

    // Catégories par type : catégories de départ + celles déjà utilisées dans le catalogue (calculées une seule fois par page)
    app()->singletonIf('rubriques.modeles', function () {
        $depart = [
            'piece' => [
                'Moteur', 'Freinage', 'Filtres', 
                'Carrosserie', 'Pneus et jantes',  'Accessoires',
            ],
            'divers' => [
                'Chaises', 'Matelas', 'Meubles', 'Électroménager', 'Vélos',
            ],
        ];

        $utilisees = \App\Models\Modele::query()
            ->whereIn('type', ['piece', 'divers'])
            ->whereNotNull('categorie')->where('categorie', '!=', '')
            ->select('type', 'categorie')->distinct()->get()
            ->groupBy('type');

        $resultat = [];
        foreach ($depart as $t => $liste) {
            $resultat[$t] = collect($liste)
                ->merge(($utilisees[$t] ?? collect())->pluck('categorie'))
                ->unique()->sort()->values()->all();
        }

        return $resultat;
    });

    $categoriesParType = app('rubriques.modeles');
@endphp

<div class="grid grid-cols-1 gap-4 md:grid-cols-2"
     x-data="{
        type: @js($typeInitial),
        categories: @js($categoriesParType),
        choix: @js($val('categorie') ?? ''),
        nouveauNom: '',
        get fiche() { return ['voiture', 'moto'].includes(this.type); },
        get stock() { return ['piece', 'divers'].includes(this.type); },
        get options() {
            const liste = [...(this.categories[this.type] ?? [])];
            if (this.choix && this.choix !== '__nouvelle__' && !liste.includes(this.choix)) liste.push(this.choix);
            return liste;
        },
        get categorieFinale() { return this.choix === '__nouvelle__' ? this.nouveauNom : this.choix; }
     }">
    <div class="form-section md:col-span-2">Identification</div>

    <div class="md:col-span-2">
        <x-input-label value="Type *" />
        <select name="type" x-model="type" @change="choix = ''; nouveauNom = ''" class="mt-1 block w-full">
            @foreach (\App\Models\Modele::TYPES as $cle => $libelle)
                <option value="{{ $cle }}">{{ $libelle }}</option>
            @endforeach
        </select>
        <x-input-error :messages="$err('type')" class="mt-2" />
    </div>

    {{-- Catégorie : liste pour les pièces détachées et les articles divers --}}
    <div class="md:col-span-2" x-show="stock">
        <x-input-label value="Catégorie *" />
        <input type="hidden" name="categorie" :value="categorieFinale" :disabled="! stock">

        <select x-model="choix" :required="stock" class="mt-1 block w-full">
            <option value="">— Choisir une catégorie —</option>
            <template x-for="c in options" :key="c">
                <option :value="c" x-text="c" :selected="c === choix"></option>
            </template>
            <option value="__nouvelle__">+ Nouvelle catégorie…</option>
        </select>

        <input type="text" x-show="choix === '__nouvelle__'" x-model="nouveauNom" maxlength="100"
               :required="stock && choix === '__nouvelle__'" placeholder="Nom de la nouvelle catégorie"
               class="mt-2 block w-full">

        <x-input-error :messages="$err('categorie')" class="mt-2" />
    </div>

    <div>
        <label class="block font-medium text-sm text-gray-700" x-text="fiche ? 'Marque *' : 'Marque (facultatif)'"></label>
        <input type="text" name="marque" value="{{ $val('marque') }}" maxlength="100" :required="fiche" class="mt-1 block w-full">
        <x-input-error :messages="$err('marque')" class="mt-2" />
    </div>

    <div>
        <label class="block font-medium text-sm text-gray-700" x-text="stock ? 'Désignation *' : 'Modèle *'"></label>
        <input type="text" name="modele" value="{{ $val('modele') }}" maxlength="100" required class="mt-1 block w-full">
        <x-input-error :messages="$err('modele')" class="mt-2" />
    </div>

    {{-- Voitures et motos : catégorie en saisie libre, comme avant --}}
    <div x-show="fiche">
        <x-input-label value="Catégorie" />
        <input type="text" name="categorie" value="{{ $val('categorie') }}" maxlength="100" list="liste-categories-modeles" :disabled="stock" class="mt-1 block w-full">
        @if ($actif && ! in_array(old('type'), ['piece', 'divers'], true))
            <x-input-error :messages="$err('categorie')" class="mt-2" />
        @endif
    </div>

    <div>
        <label class="block font-medium text-sm text-gray-700" x-text="stock ? 'Prix de vente (FCFA) *' : 'Prix de vente indicatif (FCFA)'"></label>
        <input type="number" name="prix_vente_indicatif" value="{{ $val('prix_vente_indicatif') }}" min="0" step="1" :required="stock" class="mt-1 block w-full">
        <x-input-error :messages="$err('prix_vente_indicatif')" class="mt-2" />
    </div>

    <div class="form-section md:col-span-2">Caractéristiques</div>

    {{-- Motos : cylindrée --}}
    <div class="md:col-span-2" x-show="type === 'moto'">
        <x-input-label value="Cylindrée (cm³)" />
        <x-text-input name="cylindree" type="number" min="1" max="9999" class="mt-1 block w-full" :value="$val('cylindree')" />
        <x-input-error :messages="$err('cylindree')" class="mt-2" />
    </div>

    {{-- Voitures : carburant et boîte de vitesses --}}
    <div x-show="type === 'voiture'">
        <x-input-label value="Carburant" />
        <select name="carburant" class="mt-1 block w-full">
            <option value="">—</option>
            @foreach (\App\Models\Modele::CARBURANTS as $cle => $libelle)
                <option value="{{ $cle }}" @selected($val('carburant') === $cle)>{{ $libelle }}</option>
            @endforeach
        </select>
        <x-input-error :messages="$err('carburant')" class="mt-2" />
    </div>

    <div x-show="type === 'voiture'">
        <x-input-label value="Boîte de vitesses" />
        <select name="boite" class="mt-1 block w-full">
            <option value="">—</option>
            @foreach (\App\Models\Modele::BOITES as $cle => $libelle)
                <option value="{{ $cle }}" @selected($val('boite') === $cle)>{{ $libelle }}</option>
            @endforeach
        </select>
        <x-input-error :messages="$err('boite')" class="mt-2" />
    </div>

    {{-- Pièces et articles divers : seuil d'alerte --}}
    <div class="md:col-span-2" x-show="stock">
        <x-input-label value="Seuil d'alerte du stock" />
        <x-text-input name="seuil_alerte" type="number" min="0" class="mt-1 block w-full" :value="$val('seuil_alerte') ?? 0" />
        <p class="muted" style="font-size:.75rem;margin-top:.25rem">La cloche prévient quand le stock descend à ce niveau.</p>
        <x-input-error :messages="$err('seuil_alerte')" class="mt-2" />
    </div>

    <p class="muted md:col-span-2" style="font-size:.78rem;margin:0" x-show="type === 'voiture' || type === 'moto'">
        Les fiches (une par véhicule ou par moto) sont créées par l'achat : choisis ce modèle et le nombre voulu dans la rubrique Achats.
    </p>
    <p class="muted md:col-span-2" style="font-size:.78rem;margin:0" x-show="stock">
        Le stock n'existe qu'après un achat : une fois défini ici, l'article se commande dans la rubrique Achats.
    </p>
</div>