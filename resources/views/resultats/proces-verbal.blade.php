@php
    /** @var \App\Models\ProcesVerbal $pv */
    $scrutin = $pv->scrutin;
    $edition = $scrutin->phase->edition;
    $fuseau = 'Africa/Porto-Novo';
@endphp
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Procès-verbal {{ $pv->numero }}</title>
    <style>
        body { font-family: "DejaVu Sans", Arial, sans-serif; font-size: 11pt; margin: 1.5cm; }
        @page { margin: 1cm; }
        h1 { font-size: 16pt; text-transform: uppercase; text-align: center; margin-bottom: 0.2cm; }
        p.sous-titre { text-align: center; margin-top: 0; }
        table { width: 100%; border-collapse: collapse; margin: 0.5cm 0; }
        th, td { border: 1px solid; padding: 6px 8px; text-align: left; }
        td.nombre, th.nombre { text-align: right; font-variant-numeric: tabular-nums; }
        .signatures { margin-top: 2cm; width: 100%; }
        .signatures td { border: 0; width: 50%; padding: 0; }
        .signatures td + td { text-align: right; }
        .empreinte { font-family: monospace; font-size: 9pt; word-break: break-all; }
    </style>
</head>
<body>
    <h1>Procès-verbal de dépouillement</h1>
    <p class="sous-titre">{{ $edition->libelle }} · {{ $scrutin->phase->libelle }}<br><strong>{{ $scrutin->libelle }}</strong></p>

    <p>Procès-verbal n° <strong>{{ $pv->numero }}</strong>, établi le {{ $pv->date_etablissement->timezone($fuseau)->format('d/m/Y à H:i') }}, validé le {{ $pv->date_validation->timezone($fuseau)->format('d/m/Y à H:i') }}.</p>
    <p>Scrutin ouvert du {{ $scrutin->date_heure_ouverture?->timezone($fuseau)->format('d/m/Y H:i') }} au {{ $scrutin->date_heure_fermeture?->timezone($fuseau)->format('d/m/Y H:i') }}.</p>

    <table>
        <tbody>
            <tr><th>Inscrits</th><td class="nombre">{{ $pv->nb_inscrits }}</td></tr>
            <tr><th>Votants</th><td class="nombre">{{ $pv->nb_votants }}</td></tr>
            <tr><th>Bulletins blancs</th><td class="nombre">{{ $pv->nb_blancs }}</td></tr>
            <tr><th>Suffrages exprimés</th><td class="nombre">{{ $pv->nb_exprimes }}</td></tr>
            <tr><th>Taux de participation</th><td class="nombre">{{ number_format((float) $pv->taux_participation, 2, ',', ' ') }} %</td></tr>
            <tr><th>Quorum</th><td>{{ $scrutin->quorum === null ? 'Aucun' : number_format((float) $scrutin->quorum, 2, ',', ' ').' %, '.($pv->quorum_atteint ? 'atteint' : 'non atteint') }}</td></tr>
        </tbody>
    </table>

    <table>
        <thead>
            <tr><th class="nombre">Rang</th><th>Candidate</th><th>Matricule</th><th class="nombre">Voix</th><th class="nombre">%</th><th>Résultat</th></tr>
        </thead>
        <tbody>
            @foreach ($scrutin->candidates as $candidate)
                <tr>
                    <td class="nombre">{{ $candidate->rang ?? '' }}</td>
                    <td>{{ $candidate->agent->nomComplet() }}</td>
                    <td>{{ $candidate->agent->matricule }}</td>
                    <td class="nombre">{{ $candidate->nb_voix ?? '' }}</td>
                    <td class="nombre">{{ $candidate->pourcentage === null ? '' : number_format((float) $candidate->pourcentage, 2, ',', ' ') }}</td>
                    <td>{{ $candidate->estActive() ? ($candidate->est_laureate ? 'Lauréate' : '') : 'Désistée' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if ($pv->motif_departage)
        <p><strong>Départage des ex-æquo par le comité d’organisation :</strong> {{ $pv->motif_departage }}</p>
    @endif

    <p>Empreinte du dernier bulletin de l’urne :<br><span class="empreinte">{{ $pv->empreinte_chaine ?? 'Aucun bulletin' }}</span></p>

    <table class="signatures"><tr><td>Pour le comité d’organisation</td><td>Le président</td></tr></table>
</body>
</html>
