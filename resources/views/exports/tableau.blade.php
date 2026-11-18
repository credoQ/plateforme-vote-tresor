<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>{{ $titre }}</title>
    <style>
        @page { margin: 1.2cm; }
        body { font-family: "DejaVu Sans", Arial, sans-serif; font-size: 9pt; }
        h1 { font-size: 13pt; margin: 0 0 4px; }
        p { margin: 0 0 12px; font-size: 8pt; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid; padding: 4px 6px; text-align: left; }
        th { font-weight: bold; }
    </style>
</head>
<body>
    <h1>{{ $titre }}</h1>
    <p>Direction générale du Trésor et de la Comptabilité publique · Femme modèle du Trésor · édité le {{ now()->timezone('Africa/Porto-Novo')->format('d/m/Y à H:i') }} · {{ count($lignes) }} ligne(s)</p>
    <table>
        <thead><tr>@foreach ($entete as $colonne)<th>{{ $colonne }}</th>@endforeach</tr></thead>
        <tbody>
            @foreach ($lignes as $ligne)
                <tr>@foreach ($ligne as $valeur)<td>{{ is_float($valeur) ? number_format($valeur, 2, ',', ' ') : $valeur }}</td>@endforeach</tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
