@use('App\Enums\Scrutins\StatutScrutin')
<x-layouts.electeur titre="Mon espace" actif="espace">
    <div class="portail__bonjour">
        <h1>Bonjour {{ $prenom }}</h1>
        @if ($affectation)<p>{{ $affectation->structure->libelle }}</p>@endif
    </div>

    @if ($femmeModele)
        <div class="alerte alerte--succes" role="status"><i data-lucide="crown"></i><div><strong>Vous êtes Femme modèle du Trésor {{ $femmeModele['annee'] }}</strong> · {{ $femmeModele['scrutin'] }}, félicitations !</div></div>
    @endif
    @if ($qualification)
        <div class="alerte alerte--succes" role="status"><i data-lucide="award"></i><div><strong>Vous êtes qualifiée pour la {{ $qualification['phase'] }}</strong> · {{ $qualification['scrutin'] }}, rang {{ $qualification['rang'] }}.</div></div>
    @endif

    @if ($inscription)
        <section @class(['scrutin-carte', 'scrutin-carte--ouvert' => $peutVoter]) aria-labelledby="mon-scrutin">
            <div class="scrutin-carte__haut">
                <div><div class="surtitre">Mon scrutin · {{ $scrutin->phase->libelle }}</div><h2 id="mon-scrutin">{{ $scrutin->libelle }}</h2></div>
                <span class="badge {{ $scrutin->statut->badge() }}">{{ $scrutin->statut->libelle() }}</span>
            </div>
            <div class="scrutin-carte__infos">
                @if ($inscription->a_vote)
                    <span><i data-lucide="circle-check"></i>Vous avez voté · accusé n° {{ $inscription->numero_accuse }}</span>
                @elseif ($scrutin->statut === StatutScrutin::Ouvert)
                    <span><i data-lucide="clock"></i>Clôture le {{ $scrutin->date_heure_fermeture->timezone('Africa/Porto-Novo')->format('d/m/Y à H:i') }}</span>
                    <span><i data-lucide="users-round"></i>{{ $nbCandidates }} candidate(s)</span>
                @elseif ($scrutin->date_heure_ouverture)
                    <span><i data-lucide="calendar"></i>Vote du {{ $scrutin->date_heure_ouverture->timezone('Africa/Porto-Novo')->format('d/m/Y à H:i') }} au {{ $scrutin->date_heure_fermeture->timezone('Africa/Porto-Novo')->format('d/m/Y à H:i') }}</span>
                @else
                    <span><i data-lucide="calendar"></i>Dates du vote à venir</span>
                @endif
            </div>
            @if ($peutVoter)
                <a href="{{ route('vote') }}" class="btn btn-primaire btn--lg"><i data-lucide="vote"></i>Voter maintenant</a>
            @elseif ($inscription->a_vote)
                <a href="{{ route('vote') }}" class="btn btn-secondaire"><i data-lucide="receipt"></i>Voir mon accusé de vote</a>
            @endif
        </section>
    @else
        <section class="carte" aria-label="Mon scrutin">
            <div class="etat-vide"><i data-lucide="calendar-clock"></i><p>Aucun scrutin ne vous concerne pour la phase en cours.</p></div>
        </section>
    @endif

    <div class="portail__deux">
        @foreach ($candidatures as $candidature)
            <section class="carte">
                <div class="carte__entete"><h2>Ma candidature</h2></div>
                <div class="carte__corps candidature">
                    <div>
                        <p><strong>{{ $candidature->scrutin->libelle }}</strong> · {{ $candidature->scrutin->phase->libelle }}</p>
                        @if ($candidature->estActive())
                            <p class="aide">{{ $candidature->origine->libelle() }}</p>
                        @else
                            <p class="aide">Candidature retirée le {{ $candidature->date_desistement->timezone('Africa/Porto-Novo')->format('d/m/Y à H:i') }}.</p>
                        @endif
                    </div>
                    @if ($candidature->estActive())
                        <button type="button" class="btn btn-danger-contour" data-ouvrir="desister-{{ $candidature->id }}"><i data-lucide="user-x"></i>Retirer ma candidature</button>
                    @else
                        <form method="POST" action="{{ route('candidatures.reactiver', $candidature) }}">
                            @csrf
                            <button type="submit" class="btn btn-secondaire"><i data-lucide="user-check"></i>Réactiver ma candidature</button>
                        </form>
                    @endif
                </div>
            </section>
        @endforeach

        <section class="carte">
            <div class="carte__entete"><h2>Résultats publiés</h2></div>
            @if ($resultatsPublies->isEmpty())
                <div class="etat-vide"><i data-lucide="trophy"></i><p>Aucun résultat publié pour l’instant.</p></div>
            @else
                <div class="carte__corps liste-resultats">
                    @foreach ($resultatsPublies as $resultat)
                        <a href="{{ route('exports.resultats', [$resultat, 'format' => 'pdf']) }}">
                            <span>{{ $resultat->libelle }}<small>{{ $resultat->phase->libelle }}@if ($resultat->procesVerbal?->date_publication) · publié le {{ $resultat->procesVerbal->date_publication->timezone('Africa/Porto-Novo')->format('d/m/Y') }}@endif</small></span>
                            <span class="lien"><i data-lucide="file-down"></i>PDF</span>
                        </a>
                    @endforeach
                </div>
            @endif
        </section>
    </div>

    <x-slot:modales>
        @foreach ($candidatures->filter->estActive() as $candidature)
            <div class="voile" id="desister-{{ $candidature->id }}" hidden>
                <div class="modale" role="dialog" aria-modal="true" aria-labelledby="titre-desister-{{ $candidature->id }}">
                    <form method="POST" action="{{ route('candidatures.desister', $candidature) }}">
                        @csrf
                        <div class="modale__entete">
                            <span class="pastille-icone pastille-icone--danger"><i data-lucide="user-x"></i></span>
                            <div><h2 id="titre-desister-{{ $candidature->id }}">Retirer votre candidature ?</h2><p>{{ $candidature->scrutin->libelle }}</p></div>
                        </div>
                        <div class="modale__corps">
                            <p>Vous n’apparaîtrez plus sur la liste des candidates et ne pourrez recevoir aucune voix. Vous restez électrice et pouvez réactiver votre candidature jusqu’à l’ouverture du scrutin.</p>
                            <label class="libelle" for="motif-{{ $candidature->id }}">Motif (facultatif)</label>
                            <textarea id="motif-{{ $candidature->id }}" name="motif" class="champ" maxlength="500"></textarea>
                        </div>
                        <div class="modale__pied">
                            <button type="button" class="btn btn-secondaire" data-fermer>Garder ma candidature</button>
                            <button type="submit" class="btn btn-danger"><i data-lucide="user-x"></i>Retirer ma candidature</button>
                        </div>
                    </form>
                </div>
            </div>
        @endforeach
    </x-slot:modales>
</x-layouts.electeur>
