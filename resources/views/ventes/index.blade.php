@php
    $statuts = ['validee' => 'Validée', 'annulee' => 'Annulée'];
    $venteActive = old('_modal') === 'creer-vente';
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="page-head">
            <div>
                <h1 class="page-title">Ventes</h1>
                <p class="page-sub">{{ $ventes->total() }} vente(s) enregistrée(s)</p>
            </div>
            <div class="page-actions">
                <button type="button" x-data="" x-on:click.prevent="$dispatch('open-modal', 'creer-vente')" class="btn btn-primary">
                    <x-icon name="plus" /> Nouvelle vente
                </button>
            </div>
        </div>
    </x-slot>

    @include('partials.messages')

    <form method="GET" action="{{ route('ventes.index') }}" class="toolbar">
        <div class="search">
            <x-icon name="search" />
            <input type="text" name="q" value="{{ $recherche }}" placeholder="N° de vente, client ou téléphone">
        </div>
        <select name="statut" style="min-width:11rem">
            <option value="">Tous les statuts</option>
            @foreach ($statuts as $valeur => $libelle)
                <option value="{{ $valeur }}" @selected($statut === $valeur)>{{ $libelle }}</option>
            @endforeach
        </select>
        <button type="submit" class="btn btn-ghost">Filtrer</button>
        @if ($recherche !== '' || $statut)
            <a href="{{ route('ventes.index') }}" class="btn btn-ghost">Effacer</a>
        @endif
    </form>

    <div class="card" style="overflow-x:auto">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Vente</th>
                    <th>Client</th>
                    <th class="num">Lignes</th>
                    <th class="num">Total</th>
                    <th>Statut</th>
                    <th class="num">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($ventes as $vente)
                    <tr>
                        <td>
                            <a href="{{ route('ventes.show', $vente) }}" class="person-name link">{{ $vente->numero }}</a>
                            <div class="person-sub">{{ $vente->date_vente->format('d/m/Y H:i') }}</div>
                        </td>
                        <td>
                            <div class="person">
                                <span class="avatar avatar-soft">{{ mb_strtoupper(mb_substr($vente->client->nom, 0, 1)) }}</span>
                                <div>
                                    <div class="person-name">{{ $vente->client->nom_complet }}</div>
                                    <div class="person-sub">{{ $vente->client->telephone }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="num">{{ $vente->details_count }}</td>
                        <td class="num" style="font-weight:800">{{ number_format($vente->total, 0, ',', ' ') }}</td>
                        <td>
                            <span class="badge {{ $vente->statut === 'validee' ? 'badge-green' : 'badge-red' }}">
                                {{ $statuts[$vente->statut] ?? $vente->statut }}
                            </span>
                        </td>
                        <td class="num">
                            <div class="row-actions">
                                <a href="{{ route('ventes.show', $vente) }}" class="icon-btn icon-btn-view" title="Voir la vente"><x-icon name="eye" /></a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty">Aucune vente trouvée.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <p class="muted" style="font-size:.78rem;margin-top:.6rem">Montants en FCFA. Une vente ne se modifie pas : en cas d'erreur, on l'annule.</p>

    <div class="mt-4">{{ $ventes->links() }}</div>

    {{-- Fenêtre : nouvelle vente --}}
    <x-modal name="creer-vente" maxWidth="2xl" :show="$venteActive && $errors->any()" focusable>
        <form method="POST" action="{{ route('ventes.store') }}" class="p-6"
              x-data="venteForm(@js($articles), @js(old('lignes', [])), @js((float) old('remise', 0)), @js(old('mode_paiement', 'especes')), @js($services), @js($nouveauService))">
            @csrf
            <input type="hidden" name="_modal" value="creer-vente">

            <h2 class="modal-title">Nouvelle vente</h2>

            @if ($venteActive && $errors->any())
                <div class="flash flash-error" style="align-items:flex-start">
                    <x-icon name="warning" />
                    <ul>
                        @foreach ($errors->all() as $erreur)
                            <li>{{ $erreur }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div>
                <x-input-label value="Client *" />
                <select name="client_id" class="mt-1 block w-full" required>
                    <option value="">Choisir un client...</option>
                    @foreach ($clients as $client)
                        <option value="{{ $client->id }}" @selected($venteActive && (int) old('client_id') === $client->id)>
                            {{ $client->nom_complet }} · {{ $client->telephone }}
                        </option>
                    @endforeach
                </select>
                @if ($clients->isEmpty())
                    <p class="muted" style="font-size:.78rem;margin-top:.3rem">Aucun client : crée d'abord un client dans la rubrique Clients.</p>
                @endif
            </div>

            <div style="margin-top:1.25rem">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.6rem">
                    <x-input-label value="Articles et prestations vendus *" />
                    <button type="button" x-on:click="ajouter()" class="btn btn-ghost" style="padding:.35rem .8rem"><x-icon name="plus" /> Ajouter une ligne</button>
                </div>

                {{-- Noms de prestations déjà utilisés, proposés à la saisie --}}
                <datalist id="liste-services">
                    <template x-for="s in services" :key="s.nom">
                        <option :value="s.nom"></option>
                    </template>
                </datalist>

                {{-- Champs réellement envoyés au serveur : une ligne par véhicule ou moto, comme avant --}}
                <template x-for="(e, k) in flat" :key="k">
                    <div>
                        <input type="hidden" :name="`lignes[${k}][type]`" :value="e.type">
                        <template x-if="e.type !== 'service'">
                            <input type="hidden" :name="`lignes[${k}][id]`" :value="e.id">
                        </template>
                        <template x-if="e.type === 'service'">
                            <input type="hidden" :name="`lignes[${k}][designation]`" :value="e.designation">
                        </template>
                        <input type="hidden" :name="`lignes[${k}][quantite]`" :value="e.quantite">
                        <input type="hidden" :name="`lignes[${k}][prix_unitaire]`" :value="e.prix">
                    </div>
                </template>

                <div style="display:grid;gap:.7rem">
                    <template x-for="(l, i) in lignes" :key="i">
                        <div style="border:1px solid var(--line);border-radius:.9rem;padding:.8rem;background:#fbfbfe">
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-6">
                                <div class="sm:col-span-2">
                                    <select x-model="l.type" x-on:change="changerType(i)" class="block w-full">
                                        <option value="vehicule">Véhicule</option>
                                        <option value="moto">Moto</option>
                                        <option value="piece">Pièce</option>
                                        <option value="divers">Article divers</option>
                                        <option value="service">Prestation</option>
                                    </select>
                                </div>

                                {{-- Véhicule ou moto : on choisit le modèle, puis la quantité, puis chaque unité --}}
                                <template x-if="estFiche(l)">
                                    <div class="sm:col-span-4">
                                        <select x-model="l.groupe" x-on:change="choisirGroupe(i)" required class="block w-full">
                                            <option value="">Choisir un modèle...</option>
                                            <template x-for="g in groupes(l.type)" :key="g.nom">
                                                <option :value="g.nom" x-text="g.nom + ' — ' + g.n + (g.n > 1 ? ' disponibles' : ' disponible')"></option>
                                            </template>
                                        </select>
                                    </div>
                                </template>

                                {{-- Pièce ou article divers : on choisit l'article --}}
                                <template x-if="l.type === 'piece' || l.type === 'divers'">
                                    <div class="sm:col-span-4">
                                        <select x-model="l.id" x-on:change="choisir(l, $event.target.value)" required class="block w-full">
                                            <option value="">Choisir un article...</option>
                                            <template x-for="a in (articles[l.type] || [])" :key="a.id">
                                                <option :value="a.id" x-text="a.label"></option>
                                            </template>
                                        </select>
                                    </div>
                                </template>

                                {{-- Prestation : on écrit son nom --}}
                                <template x-if="l.type === 'service'">
                                    <div class="sm:col-span-4">
                                        <input type="text" x-model="l.designation" x-on:input="suggererPrix(l)"
                                               list="liste-services" maxlength="255"
                                               placeholder="Nom de la prestation (ex. Dédouanement)"
                                               required class="block w-full">
                                    </div>
                                </template>

                                {{-- Pièce, article divers ou prestation : quantité et prix --}}
                                <template x-if="! estFiche(l)">
                                    <div class="sm:col-span-6 grid grid-cols-1 gap-3 sm:grid-cols-6">
                                        <div class="sm:col-span-2">
                                            <label class="muted" style="font-size:.72rem;font-weight:700">Quantité</label>
                                            <input type="number" min="1" :max="stock(l)" x-model.number="l.quantite" class="block w-full">
                                        </div>

                                        <div class="sm:col-span-3">
                                            <label class="muted" style="font-size:.72rem;font-weight:700">Prix unitaire (FCFA)</label>
                                            <input type="number" min="0" step="1" x-model.number="l.prix" required class="block w-full">
                                        </div>

                                        <div class="sm:col-span-1" style="display:flex;align-items:flex-end;justify-content:flex-end">
                                            <button type="button" x-show="lignes.length > 1" x-on:click="retirer(i)"
                                                    class="icon-btn icon-btn-danger" title="Retirer cette ligne"><x-icon name="trash" /></button>
                                        </div>
                                    </div>
                                </template>

                                {{-- Véhicule ou moto : quantité, puis une ligne par unité --}}
                                <template x-if="estFiche(l)">
                                    <div class="sm:col-span-6" style="display:grid;gap:.7rem">
                                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-6">
                                            <div class="sm:col-span-2">
                                                <label class="muted" style="font-size:.72rem;font-weight:700">Quantité</label>
                                                <input type="number" min="1" :max="capacite(i)" x-model.number="l.quantite"
                                                       x-on:change="ajusterUnites(i)" :disabled="! l.groupe" class="block w-full">
                                            </div>

                                            <div class="sm:col-span-3" style="display:flex;align-items:flex-end">
                                                <p class="muted" style="font-size:.78rem;margin:0 0 .6rem"
                                                   x-text="l.groupe ? capacite(i) + ' unité(s) disponible(s) pour ce modèle' : 'Choisis d abord un modèle'"></p>
                                            </div>

                                            <div class="sm:col-span-1" style="display:flex;align-items:flex-end;justify-content:flex-end">
                                                <button type="button" x-show="lignes.length > 1" x-on:click="retirer(i)"
                                                        class="icon-btn icon-btn-danger" title="Retirer cette ligne"><x-icon name="trash" /></button>
                                            </div>
                                        </div>

                                        <template x-for="(u, k) in l.unites" :key="k">
                                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-6">
                                                <div class="sm:col-span-4">
                                                    <label class="muted" style="font-size:.72rem;font-weight:700" x-text="'Unité ' + (k + 1)"></label>
                                                    <select x-model="u.id" x-on:change="choisirUnite(i, k, $event.target.value)" required class="block w-full">
                                                        <option value="">Choisir l'unité...</option>
                                                        <template x-for="a in libres(i, k)" :key="a.id">
                                                            <option :value="a.id" x-text="a.label"></option>
                                                        </template>
                                                    </select>
                                                </div>

                                                <div class="sm:col-span-2">
                                                    <label class="muted" style="font-size:.72rem;font-weight:700">Prix unitaire (FCFA)</label>
                                                    <input type="number" min="0" step="1" x-model.number="u.prix" required class="block w-full">
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2" style="margin-top:1.25rem">
                <div>
                    <x-input-label value="Mode de paiement *" />
                    <select name="mode_paiement" x-model="mode" class="mt-1 block w-full">
                        <option value="especes">Espèces</option>
                        <option value="orange_money">Orange Money</option>
                    </select>
                </div>

                <div x-show="mode === 'orange_money'">
                    <x-input-label value="Référence de la transaction *" />
                    <x-text-input name="reference_transaction" type="text" class="mt-1 block w-full"
                                  :value="$venteActive ? old('reference_transaction') : ''" />
                </div>

                <div>
                    <x-input-label value="Remise (FCFA)" />
                    <input type="number" min="0" step="1" name="remise" x-model.number="remise" class="mt-1 block w-full">
                </div>

                <div>
                    <x-input-label value="Notes" />
                    <x-text-input name="notes" type="text" class="mt-1 block w-full" :value="$venteActive ? old('notes') : ''" />
                </div>
            </div>

            {{-- Récapitulatif --}}
            <div style="margin-top:1.25rem;border-radius:.9rem;background:#0b1220;color:#fff;padding:1rem 1.25rem">
                <div style="display:flex;justify-content:space-between;font-size:.88rem;color:#aab4cf">
                    <span>Sous-total</span><span x-text="fmt(sousTotal) + ' FCFA'"></span>
                </div>
                <div style="display:flex;justify-content:space-between;font-size:.88rem;color:#aab4cf;margin-top:.25rem">
                    <span>Remise</span><span x-text="'− ' + fmt(remise || 0) + ' FCFA'"></span>
                </div>
                <div style="display:flex;justify-content:space-between;align-items:baseline;margin-top:.6rem;padding-top:.6rem;border-top:1px solid rgba(255,255,255,.14)">
                    <span style="font-weight:700">Total à encaisser</span>
                    <span style="font-size:1.4rem;font-weight:800;color:#f5a524" x-text="fmt(total) + ' FCFA'"></span>
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <x-secondary-button x-on:click="$dispatch('close')">Annuler</x-secondary-button>
                <x-primary-button>Valider la vente</x-primary-button>
            </div>
        </form>
    </x-modal>

    {{-- Venant de la page Prestations : on ouvre directement la fenêtre avec une ligne de prestation --}}
    @if ($nouveauService)
        <div x-data x-init="$nextTick(() => $dispatch('open-modal', 'creer-vente'))"></div>
    @endif

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('venteForm', (articles, anciennes, remise, mode, services, commencerParService) => {
                const estFiche = (type) => type === 'vehicule' || type === 'moto';
                const vide = (type = 'piece') => ({ type, id: '', designation: '', quantite: 1, prix: 0, groupe: '', unites: [] });

                // Reconstruit les lignes du formulaire (après une erreur de validation, par exemple)
                const reconstruire = () => {
                    const depart = [vide(commencerParService ? 'service' : 'vehicule')];
                    if (!anciennes.length) { return depart; }

                    const lignes = [];
                    anciennes.forEach(l => {
                        if (estFiche(l.type)) {
                            // Le serveur reçoit une ligne par unité : on les regroupe par modèle
                            const a = (articles[l.type] || []).find(x => String(x.id) === String(l.id));
                            if (!a) { return; }
                            let ligne = lignes.find(x => x.type === l.type && x.groupe === a.groupe);
                            if (!ligne) { ligne = vide(l.type); ligne.groupe = a.groupe; lignes.push(ligne); }
                            ligne.unites.push({ id: String(a.id), prix: Number(l.prix_unitaire || 0) });
                            ligne.quantite = ligne.unites.length;
                            return;
                        }

                        // Le serveur ne connaît que « piece » : on retrouve l'article divers grâce à son identifiant
                        const divers = l.type === 'piece' && (articles.divers || []).some(a => String(a.id) === String(l.id));
                        lignes.push({
                            ...vide(divers ? 'divers' : l.type),
                            id: String(l.id ?? ''),
                            designation: l.designation || '',
                            quantite: Number(l.quantite || 1),
                            prix: Number(l.prix_unitaire || 0),
                        });
                    });

                    return lignes.length ? lignes : depart;
                };

                return {
                    articles,
                    services,
                    mode,
                    remise,
                    lignes: [],

                    init() { this.lignes = reconstruire(); },

                    estFiche(l) { return estFiche(l.type); },

                    ajouter() { this.lignes.push(vide('piece')); },
                    retirer(i) { this.lignes.splice(i, 1); },

                    changerType(i) {
                        const l = this.lignes[i];
                        l.id = ''; l.designation = ''; l.prix = 0; l.quantite = 1; l.groupe = ''; l.unites = [];
                    },

                    // ----- Véhicules et motos : modèle, quantité, puis chaque unité -----
                    groupes(type) {
                        const compte = {};
                        (this.articles[type] || []).forEach(a => { compte[a.groupe] = (compte[a.groupe] || 0) + 1; });
                        return Object.entries(compte).map(([nom, n]) => ({ nom, n }));
                    },

                    // Unités déjà choisies dans les autres emplacements de la vente
                    prises(i, k) {
                        const type = this.lignes[i].type;
                        const ids = new Set();
                        this.lignes.forEach((x, j) => {
                            if (x.type !== type) { return; }
                            (x.unites || []).forEach((u, m) => {
                                if (!(j === i && m === k) && u.id) { ids.add(String(u.id)); }
                            });
                        });
                        return ids;
                    },

                    libres(i, k) {
                        const l = this.lignes[i];
                        const prises = this.prises(i, k);
                        return (this.articles[l.type] || []).filter(a => a.groupe === l.groupe && !prises.has(String(a.id)));
                    },

                    // Nombre d'unités encore vendables pour ce modèle (hors celles prises par les autres lignes)
                    capacite(i) {
                        const l = this.lignes[i];
                        const total = (this.articles[l.type] || []).filter(a => a.groupe === l.groupe).length;
                        let autres = 0;
                        this.lignes.forEach((x, j) => {
                            if (j !== i && x.type === l.type && x.groupe === l.groupe) { autres += (x.unites || []).length; }
                        });
                        return Math.max(total - autres, 0);
                    },

                    choisirGroupe(i) {
                        const l = this.lignes[i];
                        l.quantite = 1;
                        l.unites = [];
                        if (l.groupe) { this.ajusterUnites(i); }
                    },

                    ajusterUnites(i) {
                        const l = this.lignes[i];
                        const q = Math.max(1, Math.min(Number(l.quantite) || 1, this.capacite(i)));

                        while (l.unites.length > q) { l.unites.pop(); }
                        while (l.unites.length < q) {
                            const libre = this.libres(i, l.unites.length)[0];
                            if (!libre) { break; }
                            l.unites.push({ id: String(libre.id), prix: libre.prix });
                        }

                        l.quantite = l.unites.length;
                    },

                    choisirUnite(i, k, valeur) {
                        const l = this.lignes[i];
                        const a = (this.articles[l.type] || []).find(x => String(x.id) === String(valeur));
                        l.unites[k].id = valeur;
                        l.unites[k].prix = a ? a.prix : 0;
                    },

                    // ----- Pièces, articles divers et prestations -----
                    trouver(l) { return (this.articles[l.type] || []).find(a => String(a.id) === String(l.id)); },
                    choisir(l, valeur) {
                        l.id = valeur;
                        const a = this.trouver(l);
                        l.quantite = 1;
                        l.prix = a ? a.prix : 0;
                    },
                    // Une prestation déjà vendue propose son dernier prix (modifiable)
                    suggererPrix(l) {
                        const s = this.services.find(x => x.nom === l.designation);
                        if (s && s.prix > 0 && !Number(l.prix)) { l.prix = s.prix; }
                    },
                    stock(l) {
                        if (l.type === 'service') { return 999999; }
                        const a = this.trouver(l);
                        return a ? a.stock : 1;
                    },

                    // Lignes réellement envoyées au serveur : une par véhicule ou moto, quantité 1
                    get flat() {
                        const sortie = [];
                        this.lignes.forEach(l => {
                            if (estFiche(l.type)) {
                                (l.unites || []).forEach(u => sortie.push({ type: l.type, id: u.id, quantite: 1, prix: u.prix }));
                            } else if (l.type === 'service') {
                                sortie.push({ type: 'service', id: '', designation: l.designation, quantite: l.quantite, prix: l.prix });
                            } else {
                                // Un article divers est enregistré comme une pièce : le type envoyé est « piece »
                                sortie.push({ type: 'piece', id: l.id, quantite: l.quantite, prix: l.prix });
                            }
                        });
                        return sortie;
                    },

                    get sousTotal() {
                        return this.flat.reduce((s, e) => s + (Number(e.prix) || 0) * (Number(e.quantite) || 0), 0);
                    },
                    get total() { return Math.max(this.sousTotal - (Number(this.remise) || 0), 0); },
                    fmt(n) { return new Intl.NumberFormat('fr-FR').format(Math.round(n)); },
                };
            });
        });
    </script>
</x-app-layout>