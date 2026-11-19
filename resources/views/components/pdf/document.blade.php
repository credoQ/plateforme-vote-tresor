@props(['titre', 'type', 'qr' => null, 'editeLe', 'editePar' => null])
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>{{ $titre }}</title>
    <style>
        @page { margin: 112px 42px 150px 42px; }
        body { font-family: "DejaVu Sans", Arial, sans-serif; font-size: 9.5pt; color: #1f2a37; }
        .entete { position: fixed; top: -92px; left: 0; right: 0; height: 82px; }
        .entete table { width: 100%; border-collapse: collapse; }
        .entete td { border: 0; padding: 0; vertical-align: middle; }
        .entete .ministere { width: 175pt; }
        .entete .iso { height: 38pt; }
        .entete .dgtcp { width: 159pt; }
        .tricolore { width: 100%; border-collapse: collapse; margin-top: 8px; }
        .tricolore td { height: 3px; padding: 0; border: 0; }
        .vert { background: #008751; }
        .jaune { background: #fcd116; }
        .rouge { background: #e8112d; }
        .pied { position: fixed; bottom: -132px; left: 0; right: 0; height: 120px; }
        .pied table { width: 100%; border-collapse: collapse; }
        .pied td { border: 0; padding: 0; vertical-align: middle; }
        .qr { width: 58px; height: 58px; padding: 4px; border: 1px solid #d8dde3; border-radius: 6px; }
        .verification { padding-left: 10px; font-size: 7.5pt; color: #5b6573; }
        .verification strong { display: block; font-size: 8.5pt; color: #1f2a37; margin-bottom: 2px; }
        .edition { border-top: 1px solid #e3e7eb; margin-top: 8px; padding-top: 6px; font-size: 7.5pt; color: #5b6573; }
        .edition strong { color: #1f2a37; }
        .mini-tricolore { width: 72px; border-collapse: collapse; margin: 0 auto; }
        .mini-tricolore td { height: 3px; padding: 0; }
        .badge { display: inline-block; padding: 3px 10px; border: 1px solid #9fd3b5; border-radius: 10px; background: #e8f5ee; color: #0b5d2e; font-size: 7.5pt; font-weight: bold; letter-spacing: 1px; text-transform: uppercase; }
        h1 { font-size: 14pt; margin: 6px 0 2px; text-transform: uppercase; }
        .sous-titre { margin: 0 0 12px; color: #5b6573; }
        table.donnees { width: 100%; border-collapse: collapse; margin: 10px 0; }
        table.donnees th, table.donnees td { border: 1px solid #d8dde3; padding: 4px 6px; text-align: left; }
        table.donnees thead th { background: #0b4f2a; color: #ffffff; font-size: 8pt; text-transform: uppercase; }
        table.donnees .nombre { text-align: right; }
        .empreinte { font-family: monospace; font-size: 7.5pt; word-break: break-all; }
    </style>
</head>
<body>
    <div class="entete">
        <table>
            <tr>
                <td><img src="{{ $images['ministere'] }}" class="ministere" alt=""></td>
                <td style="text-align:center"><img src="{{ $images['iso'] }}" class="iso" alt=""></td>
                <td style="text-align:right"><img src="{{ $images['dgtcp'] }}" class="dgtcp" alt=""></td>
            </tr>
        </table>
        <table class="tricolore"><tr><td class="vert"></td><td class="jaune"></td><td class="rouge"></td></tr></table>
    </div>

    <div class="pied">
        <table>
            <tr>
                @if ($qr)
                    <td style="width:68px"><img src="{{ $qr }}" class="qr" alt="QR code de vérification"></td>
                    <td class="verification"><strong>Vérification en ligne</strong>Scannez ce code pour contrôler l’authenticité du document auprès du Trésor public.</td>
                @endif
            </tr>
        </table>
        <table class="edition">
            <tr>
                <td style="width:62%">Femme modèle du Trésor · édité le <strong>{{ $editeLe->timezone('Africa/Porto-Novo')->format('d/m/Y à H:i') }}</strong>@if ($editePar) par <strong>{{ $editePar }}</strong>@endif</td>
                <td style="width:14%"><table class="mini-tricolore"><tr><td class="vert"></td><td class="jaune"></td><td class="rouge"></td></tr></table></td>
                <td style="width:24%;text-align:right"><span class="badge">{{ $type }}</span></td>
            </tr>
        </table>
    </div>

    {{ $slot }}
</body>
</html>
