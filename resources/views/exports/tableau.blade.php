<x-pdf.document :titre="$titre" :type="$type" :qr="$qr" :edite-le="$editeLe" :edite-par="$editePar">
    <h1>{{ $titre }}</h1>
    <p class="sous-titre">{{ $sousTitre }} · {{ count($lignes) }} ligne(s)</p>
    <table class="donnees">
        <thead><tr>@foreach ($entete as $colonne)<th>{{ $colonne }}</th>@endforeach</tr></thead>
        <tbody>
            @foreach ($lignes as $ligne)
                <tr>@foreach ($ligne as $valeur)<td @class(['nombre' => is_int($valeur) || is_float($valeur)])>{{ is_float($valeur) ? number_format($valeur, 2, ',', ' ') : $valeur }}</td>@endforeach</tr>
            @endforeach
        </tbody>
    </table>
</x-pdf.document>
