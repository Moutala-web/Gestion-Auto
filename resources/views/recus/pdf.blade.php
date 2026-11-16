@php
    $fmt = fn ($n) => number_format((float) $n, 0, ',', "\u{00A0}");
    $devise = $parametre->devise ?: 'FCFA';
    $paiement = $recu->paiement;
    $vente = $recu->vente;
    $location = $recu->location;
    $client = $vente?->client ?? $location?->client;
    $annule = $paiement->statut === 'annule';
    $modes = ['especes' => 'Espèces', 'orange_money' => 'Orange Money'];
    $objets = [
        'vente' => 'Paiement de vente',
        'location' => 'Paiement de location',
        'caution' => 'Caution',
    ];

    // Pour une location : cumul payé jusqu'à ce paiement et reste à payer à cette date
    if ($location) {
        $cumul = (float) $location->paiements
            ->where('type', $paiement->type)
            ->where('statut', 'valide')
            ->where('id', '<=', $paiement->id)
            ->sum('montant');
        $plafond = $paiement->type === 'caution' ? (float) $location->caution : (float) $location->tarif;
        $reste = max($plafond - $cumul, 0);
    }
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Reçu {{ $recu->numero }}</title>
    <style>
        @page { margin: 26px 30px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9.5px; color: #1b2236; margin: 0; }
        table { border-collapse: collapse; width: 100%; }
        td, th { vertical-align: top; }
        .muted { color: #6a7288; }
        .right { text-align: right; }
        .center { text-align: center; }
        .bold { font-weight: bold; }

        .entete td { padding-bottom: 10px; border-bottom: 2px solid #f5a524; }
        .logo { max-height: 46px; max-width: 130px; }
        .societe { font-size: 14px; font-weight: bold; }
        .titre { font-size: 17px; font-weight: bold; letter-spacing: 1px; }
        .numero { font-size: 11px; font-weight: bold; color: #b86e00; }

        .bloc { margin-top: 12px; }
        .bloc-titre { font-size: 8px; font-weight: bold; color: #6a7288; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 4px; }

        .lignes th { background: #f3f4f8; text-align: left; font-size: 8px; text-transform: uppercase; padding: 5px 6px; color: #6a7288; }
        .lignes td { padding: 5px 6px; border-bottom: 1px solid #e7e9f1; }

        .totaux td { padding: 3px 6px; }
        .montant-box { margin-top: 12px; border: 2px solid #0b1220; border-radius: 6px; padding: 9px 12px; }
        .montant { font-size: 17px; font-weight: bold; }

        .annule { border: 2px solid #c62828; color: #c62828; text-align: center; font-weight: bold; font-size: 12px; padding: 6px; margin-top: 10px; }
        .note { margin-top: 10px; padding: 7px 9px; background: #f3f4f8; border-radius: 5px; font-size: 8.5px; color: #4a5268; }
        .signatures td { width: 50%; padding-top: 34px; text-align: center; font-size: 8.5px; color: #6a7288; }
        .pied { margin-top: 14px; text-align: center; font-size: 8px; color: #8089a1; }
    </style>
</head>
<body>

    <table class="entete">
        <tr>
            <td style="width:58%">
                @if ($logo)
                    <img src="{{ $logo }}" class="logo" alt=""><br>
                @endif
                <span class="societe">{{ $parametre->nom_entreprise }}</span><br>
                @if ($parametre->adresse)<span class="muted">{!! nl2br(e($parametre->adresse)) !!}</span><br>@endif
                @if ($parametre->telephone)<span class="muted">Tél. : {{ $parametre->telephone }}</span><br>@endif
                @if ($parametre->email)<span class="muted">{{ $parametre->email }}</span>@endif
            </td>
            <td class="right">
                <span class="titre">REÇU</span><br>
                <span class="numero">N° {{ $recu->numero }}</span><br>
                <span class="muted">Émis le {{ $recu->date_generation->format('d/m/Y') }}</span>
            </td>
        </tr>
    </table>

    @if ($annule)
        <div class="annule">PAIEMENT ANNULÉ — ce reçu n'a plus de valeur</div>
    @endif

    <div class="bloc">
        <div class="bloc-titre">Client</div>
        <span class="bold">{{ $client?->nom_complet ?? '—' }}</span>
        @if ($client?->telephone) <span class="muted">· {{ $client->telephone }}</span> @endif
    </div>

    @if ($vente)
        <div class="bloc">
            <div class="bloc-titre">Vente {{ $vente->numero }} · {{ $vente->date_vente->format('d/m/Y') }}</div>
            <table class="lignes">
                <thead>
                    <tr>
                        <th>Désignation</th>
                        <th class="right">Qté</th>
                        <th class="right">P.U.</th>
                        <th class="right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($vente->details as $detail)
                        <tr>
                            <td>{{ $detail->designation }}</td>
                            <td class="right">{{ $detail->quantite }}</td>
                            <td class="right">{{ $fmt($detail->prix_unitaire) }}</td>
                            <td class="right bold">{{ $fmt($detail->total) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <table class="totaux" style="margin-top:4px">
                <tr><td class="right muted">Sous-total</td><td class="right" style="width:30%">{{ $fmt($vente->sous_total) }} {{ $devise }}</td></tr>
                @if ((float) $vente->remise > 0)
                    <tr><td class="right muted">Remise</td><td class="right">− {{ $fmt($vente->remise) }} {{ $devise }}</td></tr>
                @endif
                <tr><td class="right bold">Total de la vente</td><td class="right bold">{{ $fmt($vente->total) }} {{ $devise }}</td></tr>
            </table>
        </div>
    @endif

    @if ($location)
        <div class="bloc">
            <div class="bloc-titre">Location {{ $location->numero }}</div>
            <table class="lignes">
                <tbody>
                    <tr>
                        <td class="muted" style="width:32%">Véhicule</td>
                        <td class="bold">{{ $location->vehicule->marque }} {{ $location->vehicule->modele }} ({{ $location->vehicule->reference }})</td>
                    </tr>
                    <tr>
                        <td class="muted">Période</td>
                        <td>du {{ $location->date_depart->format('d/m/Y H:i') }} au {{ $location->date_retour_prevue->format('d/m/Y H:i') }}</td>
                    </tr>
                    <tr>
                        <td class="muted">Tarif convenu</td>
                        <td>{{ $fmt($location->tarif) }} {{ $devise }}</td>
                    </tr>
                    <tr>
                        <td class="muted">Caution convenue</td>
                        <td>{{ $fmt($location->caution) }} {{ $devise }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    @endif

    <div class="montant-box">
        <table>
            <tr>
                <td>
                    <div class="bloc-titre" style="margin:0">{{ $objets[$paiement->type] ?? 'Paiement' }}</div>
                    <span class="muted">
                        {{ $modes[$paiement->mode] ?? $paiement->mode }}
                        @if ($paiement->reference_transaction) · Réf. {{ $paiement->reference_transaction }} @endif
                        · {{ $paiement->date_paiement->format('d/m/Y H:i') }}
                    </span>
                </td>
                <td class="right" style="width:42%">
                    <span class="montant">{{ $fmt($paiement->montant) }} {{ $devise }}</span>
                </td>
            </tr>
        </table>
    </div>

    @if ($location)
        <table class="totaux" style="margin-top:6px">
            <tr>
                <td class="right muted">{{ $paiement->type === 'caution' ? 'Caution versée à ce jour' : 'Total payé à ce jour' }}</td>
                <td class="right" style="width:30%">{{ $fmt($cumul) }} {{ $devise }}</td>
            </tr>
            <tr>
                <td class="right bold">{{ $paiement->type === 'caution' ? 'Caution restant à verser' : 'Reste à payer' }}</td>
                <td class="right bold">{{ $fmt($reste) }} {{ $devise }}</td>
            </tr>
        </table>

        @if ($paiement->type === 'caution')
            <div class="note">
                La caution est une somme détenue en garantie : elle est remboursée à la restitution du véhicule,
                déduction faite d'éventuelles retenues pour dégâts.
            </div>
        @endif
    @endif

    <table class="signatures">
        <tr>
            <td>Le client</td>
            <td>La direction</td>
        </tr>
    </table>

    <div class="pied">Merci de votre confiance · {{ $parametre->nom_entreprise }}</div>

</body>
</html>