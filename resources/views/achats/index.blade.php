@php
    $modes = ['especes' => 'Espèces', 'orange_money' => 'Orange Money'];
    $types = [
        'vehicule' => ['Véhicule', 'badge-yellow'],
        'moto' => ['Moto', 'badge-blue'],
        'piece' => ['Pièce', 'badge-green'],
        'divers' => ['Article divers', 'badge-blue'],
    ];
    $achatActif = old('_modal') === 'creer-achat';
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="page-head">
            <div>
                <h1 class="page-title">Achats</h1>
                <p class="page-sub">{{ $nombre }} achat(s) · total <strong>{{ number_format($total, 0, ',', ' ') }} FCFA</strong> sur la période</p>
            </div>
            <div class="page-actions">
                <button type="button" x-data="" x-on:click.prevent="$dispatch('open-modal', 'creer-achat')" class="btn btn-primary">
                    <x-icon name="plus" /> Nouvel achat
                </button>
            </div>
        </div>
    </x-slot>

    @include('partials.messages')

    <form method="GET" action="{{ route('achats.index') }}" class="toolbar">
        <div class="search">
            <x-icon name="search" />
            <input type="text" name="q" value="{{ $recherche }}" placeholder="Fournisseur, référence ou note">
        </div>
        <select name="type" style="min-width:11rem">
            <option value="">Tous les types</option>
            @foreach ($types as $cle => [$libelle])
                <option value="{{ $cle }}" @selected($type === $cle)>{{ $libelle }}</option>
            @endforeach
        </select>
        <input type="date" name="du" value="{{ $du }}" title="Du">
        <input type="date" name="au" value="{{ $au }}" title="Au">
        <button type="submit" class="btn btn-ghost">Filtrer</button>
        <a href="{{ route('achats.index') }}" class="btn btn-ghost">Mois en cours</a>
    </form>

    <div class="card" style="overflow-x:auto">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Achat</th>
                    <th>Article</th>
                    <th>Fournisseur</th>
                    <th class="num">Qté</th>
                    <th class="num">Prix unitaire</th>
                    <th class="num">Montant</th>
                    <th>Mode</th>
                    <th>Statut</th>
                    <th class="num">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($achats as $achat)
                    @php
                        $annule = $achat->statut === 'annule';
                        // Un article divers est enregistré comme une pièce : on le distingue par sa famille
                        $cleType = $achat->type_article === 'piece' && $achat->piece?->famille === 'divers' ? 'divers' : $achat->type_article;
                        [$typeLibelle, $typeClasse] = $types[$cleType] ?? [$achat->type_article, 'badge-gray'];
                    @endphp
                    <tr style="{{ $annule ? 'opacity:.55' : '' }}">
                        <td>
                            <div class="person-name">{{ $achat->numero }}</div>
                            <div class="person-sub">{{ $achat->date_achat->format('d/m/Y') }}</div>
                        </td>
                        <td>
                            <div class="person-name" style="{{ $annule ? 'text-decoration:line-through' : '' }}">{{ $achat->libelle_article }}</div>
                            <span class="badge {{ $typeClasse }}" style="margin-top:.25rem">{{ $typeLibelle }}</span>
                            @if ($annule && $achat->motif_annulation)
                                <div class="person-sub">Annulé : {{ $achat->motif_annulation }}</div>
                            @endif
                        </td>
                        <td>
                            {{ $achat->fournisseur ?: '—' }}
                            @if ($achat->reference_transaction)
                                <div class="person-sub">Réf. {{ $achat->reference_transaction }}</div>
                            @endif
                        </td>
                        <td class="num">{{ $achat->quantite }}</td>
                        <td class="num">{{ number_format($achat->prix_unitaire, 0, ',', ' ') }}</td>
                        <td class="num" style="font-weight:800">{{ number_format($achat->montant, 0, ',', ' ') }}</td>
                        <td>
                            <span class="badge {{ $achat->mode_paiement === 'orange_money' ? 'badge-yellow' : 'badge-gray' }}">
                                {{ $modes[$achat->mode_paiement] ?? $achat->mode_paiement }}
                            </span>
                        </td>
                        <td><span class="badge {{ $annule ? 'badge-red' : 'badge-green' }}">{{ $annule ? 'Annulé' : 'Valide' }}</span></td>
                        <td class="num">
                            {{-- Seul l'achat de pièces s'annule : un véhicule ou une moto acheté est dans le stock --}}
                            @if (! $annule && $achat->type_article === 'piece')
                                <div class="row-actions">
                                    <button type="button" x-data=""
                                            x-on:click.prevent="$dispatch('open-modal', 'annuler-achat-{{ $achat->id }}')"
                                            class="icon-btn icon-btn-danger" title="Annuler cet achat"><x-icon name="trash" /></button>
                                </div>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="empty">Aucun achat sur cette période.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <p class="muted" style="font-size:.78rem;margin-top:.6rem">
        Montants en FCFA. Le stock n'entre que par un achat : un véhicule ou une moto acheté crée sa fiche (une ligne par unité),
        une pièce augmente son stock. L'achat de pièces peut s'annuler ; celui d'un véhicule ou d'une moto non.
    </p>

    <div class="mt-4">{{ $achats->links() }}</div>

    {{-- Fenêtre : nouvel achat (modèle et quantité) --}}
    <x-modal name="creer-achat" maxWidth="2xl" :show="$achatActif && $errors->any()" focusable>
        <form method="POST" action="{{ route('achats.arrivage') }}" class="p-6"
              x-data="achatForm(@js($modelesAchat), @js($achatActif ? old('lignes', []) : []), @js($achatActif ? old('mode_paiement', 'especes') : 'especes'), @js($achatActif ? (bool) old('enregistrer_en_caisse', true) : true), @js($preselection))">
            @csrf
            <input type="hidden" name="_modal" value="creer-achat">

            <h2 class="modal-title" style="margin-bottom:.25rem">Nouvel achat</h2>
            <p class="muted" style="font-size:.82rem;margin:0 0 1rem">
                Choisis le modèle et le nombre voulu : les fiches sont créées automatiquement.
                Tu complètes ensuite l'immatriculation, la couleur et les autres informations de chacune dans Véhicules et Motos.
            </p>

            @include('partials.erreurs', ['modal' => 'creer-achat'])

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-6">
                <div class="sm:col-span-3">
                    <x-input-label value="Fournisseur" />
                    <x-text-input name="fournisseur" type="text" class="mt-1 block w-full" :value="$achatActif ? old('fournisseur') : ''" />
                </div>

                <div class="sm:col-span-3">
                    <x-input-label value="Date de l'achat *" />
                    <input type="date" name="date_achat" value="{{ $achatActif ? old('date_achat') : now()->toDateString() }}" class="mt-1 block w-full" required>
                </div>

                <div class="sm:col-span-6" style="border:1px solid var(--line);border-radius:.6rem;padding:.8rem 1rem;background:#fbfbfe">
                    <input type="hidden" name="enregistrer_en_caisse" value="0">
                    <label style="display:flex;gap:.6rem;align-items:flex-start;cursor:pointer;font-weight:600;font-size:.88rem">
                        <input type="checkbox" name="enregistrer_en_caisse" value="1" x-model="enCaisse" style="margin-top:.2rem">
                        <span>
                            Enregistrer le paiement en caisse
                            <span class="muted" style="display:block;font-weight:500;font-size:.78rem">
                                Décoche pour du stock déjà payé (stock de départ) : les fiches sont créées sans toucher à la caisse.
                            </span>
                        </span>
                    </label>
                </div>

                <div class="sm:col-span-3" x-show="enCaisse">
                    <x-input-label value="Mode de paiement *" />
                    <select name="mode_paiement" x-model="mode" class="mt-1 block w-full">
                        <option value="especes">Espèces</option>
                        <option value="orange_money">Orange Money</option>
                    </select>
                </div>

                <div class="sm:col-span-3" x-show="enCaisse && mode === 'orange_money'">
                    <x-input-label value="Référence de la transaction *" />
                    <x-text-input name="reference_transaction" type="text" class="mt-1 block w-full" :value="$achatActif ? old('reference_transaction') : ''" />
                </div>
            </div>

            <div style="margin-top:1.25rem">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.6rem">
                    <x-input-label value="Ce qui est acheté *" />
                    <button type="button" x-on:click="ajouter()" class="btn btn-ghost" style="padding:.35rem .8rem"><x-icon name="plus" /> Ajouter une ligne</button>
                </div>

                <div style="display:grid;gap:.7rem">
                    <template x-for="(l, i) in lignes" :key="i">
                        <div style="border:1px solid var(--line);border-radius:.9rem;padding:.8rem;background:#fbfbfe">
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-6">
                                <div class="sm:col-span-2">
                                    {{-- Un article divers est une pièce d'une autre famille : le type envoyé est « piece » --}}
                                    <input type="hidden" :name="`lignes[${i}][type]`" :value="l.type === 'divers' ? 'piece' : l.type">

                                    <select x-model="l.type" x-on:change="changerType(l)" class="block w-full">
                                        <option value="vehicule">Véhicule</option>
                                        <option value="moto">Moto</option>
                                        <option value="piece">Pièce</option>
                                        <option value="divers">Article divers</option>
                                    </select>
                                </div>

                                <div class="sm:col-span-4">
                                    <select :name="`lignes[${i}][modele_id]`" x-model="l.modele_id"
                                            x-on:change="choisir(l, $event.target.value)" required class="block w-full">
                                        <option value="">Choisir dans le catalogue...</option>
                                        <template x-for="m in liste(l)" :key="m.id">
                                            <option :value="m.id" x-text="m.label"></option>
                                        </template>
                                    </select>
                                    <p class="muted" style="font-size:.75rem;margin-top:.25rem" x-show="liste(l).length === 0">
                                        Le catalogue est vide pour ce type :
                                        <a href="{{ route('modeles.index') }}" class="link">définis-le d'abord dans le Catalogue</a>.
                                    </p>
                                </div>

                                <div class="sm:col-span-2">
                                    <label class="muted" style="font-size:.72rem;font-weight:700" x-text="estFiche(l) ? 'Nombre de fiches' : 'Quantité'"></label>
                                    <input type="number" min="1" :max="estFiche(l) ? 50 : 1000" :name="`lignes[${i}][quantite]`" x-model.number="l.quantite" class="block w-full" required>
                                </div>

                                <div class="sm:col-span-2">
                                    <label class="muted" style="font-size:.72rem;font-weight:700">Prix unitaire (FCFA)</label>
                                    <input type="number" min="1" step="1" :name="`lignes[${i}][prix_unitaire]`" x-model.number="l.prix" class="block w-full" required>
                                </div>

                                <div class="sm:col-span-2" style="display:flex;align-items:flex-end;justify-content:space-between;gap:.5rem">
                                    <div>
                                        <div class="muted" style="font-size:.72rem;font-weight:700">Sous-total</div>
                                        <div style="font-weight:800" x-text="fmt((Number(l.prix) || 0) * (Number(l.quantite) || 0)) + ' FCFA'"></div>
                                    </div>
                                    <button type="button" x-show="lignes.length > 1" x-on:click="retirer(i)"
                                            class="icon-btn icon-btn-danger" title="Retirer cette ligne"><x-icon name="trash" /></button>
                                </div>

                                {{-- Véhicule ou moto : informations communes aux fiches créées (facultatives) --}}
                                <div class="sm:col-span-3" x-show="estFiche(l)">
                                    <label class="muted" style="font-size:.72rem;font-weight:700">Année (facultatif)</label>
                                    <input type="number" :name="`lignes[${i}][annee]`" x-model="l.annee" :disabled="! estFiche(l)" class="block w-full">
                                </div>

                                <div class="sm:col-span-3" x-show="estFiche(l)">
                                    <label class="muted" style="font-size:.72rem;font-weight:700">Couleur (facultatif)</label>
                                    <input type="text" :name="`lignes[${i}][couleur]`" x-model="l.couleur" :disabled="! estFiche(l)" maxlength="50" class="block w-full">
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <div style="margin-top:1.25rem">
                <x-input-label value="Notes" />
                <x-text-input name="notes" type="text" class="mt-1 block w-full" :value="$achatActif ? old('notes') : ''" />
            </div>

            {{-- Récapitulatif --}}
            <div style="margin-top:1.25rem;border-radius:.9rem;background:#0b1220;color:#fff;padding:1rem 1.25rem">
                <div style="display:flex;justify-content:space-between;font-size:.88rem;color:#aab4cf">
                    <span>Fiches véhicules et motos créées</span><span x-text="nbFiches"></span>
                </div>
                <div style="display:flex;justify-content:space-between;font-size:.88rem;color:#aab4cf;margin-top:.25rem">
                    <span>Pièces et articles ajoutés au stock</span><span x-text="nbUnites"></span>
                </div>
                <div style="display:flex;justify-content:space-between;align-items:baseline;margin-top:.6rem;padding-top:.6rem;border-top:1px solid rgba(255,255,255,.14)">
                    <span style="font-weight:700" x-text="enCaisse ? 'Montant qui sort de la caisse' : 'Valeur reçue (rien en caisse)'"></span>
                    <span style="font-size:1.4rem;font-weight:800;color:#f5a524" x-text="fmt(total) + ' FCFA'"></span>
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <x-secondary-button x-on:click="$dispatch('close')">Annuler</x-secondary-button>
                <x-primary-button>Enregistrer l'achat</x-primary-button>
            </div>
        </form>
    </x-modal>

    {{-- Venant du catalogue ou d'un bouton « Acheter » : on ouvre directement la fenêtre d'achat --}}
    @if ($preselection)
        <div x-data x-init="$nextTick(() => $dispatch('open-modal', 'creer-achat'))"></div>
    @endif

    {{-- Fenêtres : annuler un achat de pièces (une par ligne valide) --}}
    @foreach ($achats as $achat)
        @if ($achat->statut === 'valide' && $achat->type_article === 'piece')
            @php($nomModale = 'annuler-achat-' . $achat->id)

            <x-modal :name="$nomModale" maxWidth="md" :show="old('_modal') === $nomModale && $errors->any()" focusable>
                <form method="POST" action="{{ route('achats.annuler', $achat) }}" class="p-6">
                    @csrf
                    <input type="hidden" name="_modal" value="{{ $nomModale }}">

                    <div class="confirm-icon"><x-icon name="warning" /></div>
                    <h2 class="modal-title" style="text-align:center;margin-bottom:.5rem">Annuler l'achat {{ $achat->numero }} ?</h2>
                    <p class="muted" style="font-size:.9rem;text-align:center">
                        {{ $achat->libelle_article }} — {{ number_format($achat->montant, 0, ',', ' ') }} FCFA.
                        Le montant revient en caisse et le stock est diminué.
                    </p>

                    @include('partials.erreurs', ['modal' => $nomModale])

                    <div style="margin-top:1rem">
                        <x-input-label value="Motif de l'annulation *" />
                        <x-text-input name="motif" type="text" class="mt-1 block w-full" :value="old('_modal') === $nomModale ? old('motif') : ''" required />
                    </div>

                    <div class="mt-6 flex justify-center gap-3">
                        <x-secondary-button x-on:click="$dispatch('close')">Retour</x-secondary-button>
                        <button type="submit" class="btn btn-danger">Annuler l'achat</button>
                    </div>
                </form>
            </x-modal>
        @endif
    @endforeach

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('achatForm', (modeles, anciennes, mode, enCaisse, preselection) => {
                const vide = () => ({ type: 'vehicule', modele_id: '', quantite: 1, prix: 0, annee: '', couleur: '' });

                // Ligne de départ : celle du catalogue ou du bouton « Acheter » si on en vient
                const depart = () => {
                    if (! preselection) { return vide(); }

                    const l = { ...vide(), type: preselection.type, modele_id: String(preselection.modele_id || '') };
                    const m = (modeles[l.type] || []).find(x => String(x.id) === l.modele_id);
                    l.prix = m ? m.prix : 0;

                    return l;
                };

                return {
                    modeles,
                    mode,
                    enCaisse,
                    lignes: anciennes.length
                        ? anciennes.map(l => ({
                            // Le serveur ne connaît que « piece » : on retrouve l'article divers grâce à son identifiant
                            type: (l.type === 'piece' && (modeles.divers || []).some(m => String(m.id) === String(l.modele_id))) ? 'divers' : l.type,
                            modele_id: String(l.modele_id ?? ''),
                            quantite: Number(l.quantite || 1),
                            prix: Number(l.prix_unitaire || 0),
                            annee: l.annee || '',
                            couleur: l.couleur || '',
                        }))
                        : [depart()],

                    ajouter() { this.lignes.push(vide()); },
                    retirer(i) { this.lignes.splice(i, 1); },
                    estFiche(l) { return l.type === 'vehicule' || l.type === 'moto'; },
                    liste(l) { return this.modeles[l.type] || []; },
                    changerType(l) { l.modele_id = ''; l.quantite = 1; l.prix = 0; l.annee = ''; l.couleur = ''; },

                    // Le dernier prix d'achat connu est proposé, modifiable
                    choisir(l, valeur) {
                        l.modele_id = valeur;
                        const m = this.liste(l).find(x => String(x.id) === String(valeur));
                        l.prix = m ? m.prix : 0;
                    },

                    get total() {
                        return this.lignes.reduce((s, l) => s + (Number(l.prix) || 0) * (Number(l.quantite) || 0), 0);
                    },
                    get nbFiches() {
                        return this.lignes.filter(l => this.estFiche(l)).reduce((s, l) => s + (Number(l.quantite) || 0), 0);
                    },
                    get nbUnites() {
                        return this.lignes.filter(l => ! this.estFiche(l)).reduce((s, l) => s + (Number(l.quantite) || 0), 0);
                    },
                    fmt(n) { return new Intl.NumberFormat('fr-FR').format(Math.round(n)); },
                };
            });
        });
    </script>
</x-app-layout>