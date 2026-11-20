{{-- Accusé de vote (GenererAccuseDeVote) : participation de l'électeur, jamais son choix. La date d'édition reprend
     l'heure tronquée du vote, pour ne pas révéler l'heure exacte (R43). --}}
<x-pdf.document :titre="'Accusé de vote '.$inscription->numero_accuse" type="Accusé de vote" :qr="$qr" :edite-le="$inscription->date_heure_vote">
    <h1>Accusé de vote</h1>
    <p class="sous-titre">{{ $scrutin->phase->edition->libelle }} · {{ $scrutin->phase->libelle }} · <strong>{{ $scrutin->libelle }}</strong></p>

    <p>Le vote de la personne désignée ci-dessous a bien été enregistré par la plateforme.</p>

    <table class="donnees">
        <thead><tr><th colspan="2">Votant</th></tr></thead>
        <tbody>
            <tr><td>Nom et prénoms</td><td><strong>{{ $inscription->agent->nomComplet() }}</strong></td></tr>
            <tr><td>Matricule</td><td><strong>{{ $inscription->agent->matricule }}</strong></td></tr>
            <tr><td>Poste d’affectation</td><td><strong>{{ $inscription->structure->libelle }}</strong></td></tr>
        </tbody>
    </table>

    <table class="donnees">
        <thead><tr><th colspan="2">Vote</th></tr></thead>
        <tbody>
            <tr><td>Accusé n°</td><td><strong>{{ $inscription->numero_accuse }}</strong></td></tr>
            <tr><td>Phase</td><td><strong>{{ $scrutin->phase->libelle }}</strong></td></tr>
            <tr><td>Scrutin</td><td><strong>{{ $scrutin->libelle }}</strong></td></tr>
            <tr><td>Date et heure du vote</td><td><strong>{{ $inscription->creneauDeVote() }}</strong></td></tr>
        </tbody>
    </table>

    <p><strong>Secret du vote.</strong> Ce document atteste uniquement que vous avez voté. Il ne mentionne pas votre choix, qui n’est relié à votre identité nulle part sur la plateforme. L’heure du vote est donnée à l’heure près.</p>
</x-pdf.document>
