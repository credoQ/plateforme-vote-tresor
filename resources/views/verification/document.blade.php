<x-layouts.invite titre="Vérification d’un document">
    <h2>Vérification d’un document</h2>

    @if ($conforme)
        <div class="alerte alerte--succes espace-bloc" role="status"><i data-lucide="badge-check"></i>
            <div><strong>Document authentique</strong>, émis par la plateforme et conforme à ses données.</div>
        </div>
    @else
        <div class="alerte alerte--attente espace-bloc" role="status"><i data-lucide="triangle-alert"></i>
            <div><strong>Document authentique mais dépassé</strong> : les données ont changé depuis son édition.</div>
        </div>
    @endif

    <table class="tableau espace-bloc">
        <tbody>
            <tr><td>Document</td><td><strong>{{ $type }}</strong></td></tr>
            <tr><td>Scrutin</td><td>{{ $scrutin->libelle }}</td></tr>
            <tr><td>Phase</td><td>{{ $scrutin->phase->libelle }} · {{ $scrutin->phase->edition->libelle }}</td></tr>
            @foreach ($lignes as $libelle => $valeur)
                <tr><td>{{ $libelle }}</td><td>{{ $valeur }}</td></tr>
            @endforeach
        </tbody>
    </table>
</x-layouts.invite>
