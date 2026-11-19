<x-pdf.document :titre="'Procès-verbal '.$pv->numero" type="Procès-verbal" :qr="$qr" :edite-le="$pv->date_validation" :edite-par="$pv->validePar?->name">
    <h1>Procès-verbal de dépouillement</h1>
    <p class="sous-titre">{{ $edition->libelle }} · {{ $scrutin->phase->libelle }} · <strong>{{ $scrutin->libelle }}</strong></p>

    <p>Procès-verbal n° <strong>{{ $pv->numero }}</strong>, établi le {{ $pv->date_etablissement->timezone('Africa/Porto-Novo')->format('d/m/Y à H:i') }}, validé le {{ $pv->date_validation->timezone('Africa/Porto-Novo')->format('d/m/Y à H:i') }}.<br>
        Scrutin ouvert du {{ $scrutin->date_heure_ouverture?->timezone('Africa/Porto-Novo')->format('d/m/Y H:i') }} au {{ $scrutin->date_heure_fermeture?->timezone('Africa/Porto-Novo')->format('d/m/Y H:i') }}.</p>

    <table class="donnees">
        <tbody>
            <tr><th>Inscrits</th><td class="nombre">{{ $pv->nb_inscrits }}</td></tr>
            <tr><th>Votants</th><td class="nombre">{{ $pv->nb_votants }}</td></tr>
            <tr><th>Bulletins blancs</th><td class="nombre">{{ $pv->nb_blancs }}</td></tr>
            <tr><th>Suffrages exprimés</th><td class="nombre">{{ $pv->nb_exprimes }}</td></tr>
            <tr><th>Taux de participation</th><td class="nombre">{{ number_format((float) $pv->taux_participation, 2, ',', ' ') }} %</td></tr>
            <tr><th>Quorum</th><td class="nombre">{{ $scrutin->quorum === null ? 'Aucun' : number_format((float) $scrutin->quorum, 2, ',', ' ').' %, '.($pv->quorum_atteint ? 'atteint' : 'non atteint') }}</td></tr>
        </tbody>
    </table>

    <table class="donnees">
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

    <table style="width:100%;margin-top:28px"><tr><td style="width:50%">Pour le comité d’organisation</td><td style="text-align:right">Le président</td></tr></table>
</x-pdf.document>
