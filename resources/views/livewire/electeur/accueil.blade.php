@use('App\Enums\Scrutins\StatutScrutin')
{{-- Accueil de l'espace électeur. Aucun choix de vote ne passe par ce composant.
     Pas de rafraîchissement périodique : la page se rafraîchit une seule fois, peu après la prochaine échéance du
     scrutin (ouverture ou fermeture), le temps que le planificateur change son statut. La minuterie est reprogrammée
     à chaque nouvelle échéance (clé wire:key) et annulée quand on quitte la page. --}}
<div class="portail__accueil">
    @if ($echeance)
        <div hidden wire:key="echeance-{{ $echeance['horodatage'] }}"
             x-data="{ minuteur: null, init() { this.minuteur = setTimeout(() => $wire.$refresh(), Math.min(({{ $echeance['secondes'] }} + 70) * 1000, 2147483647)) }, destroy() { clearTimeout(this.minuteur) } }"></div>
    @endif
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
            {{-- Le bulletin reste une page complète, sans Livewire : lien classique, sans wire:navigate. --}}
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
            <section class="carte" wire:key="candidature-{{ $candidature->id }}">
                <div class="carte__entete"><h2>Ma candidature</h2></div>
                <div class="carte__corps candidature">
                    <div class="cellule-photo">
                        @if ($candidature->photo)
                            <img class="vignette-photo" src="{{ $candidature->urlPhoto() }}" alt="Ma photo sur le bulletin">
                        @else
                            <span class="vignette-photo" aria-hidden="true">{{ $initiales }}</span>
                        @endif
                        <div>
                            <p><strong>{{ $candidature->scrutin->libelle }}</strong> · {{ $candidature->scrutin->phase->libelle }}</p>
                            @if ($candidature->estActive())
                                <p class="aide">{{ $candidature->origine->libelle() }} · {{ $candidature->photo ? 'votre photo apparaît sur le bulletin' : 'vos initiales apparaissent sur le bulletin' }}</p>
                            @else
                                <p class="aide">Candidature retirée le {{ $candidature->date_desistement->timezone('Africa/Porto-Novo')->format('d/m/Y à H:i') }}.</p>
                            @endif
                        </div>
                    </div>
                    @if ($candidature->estActive())
                        <div class="candidature__actions">
                        <button type="button" class="btn btn-secondaire" x-on:click="Livewire.dispatch('ouvrir-photo-candidate', { candidate: {{ $candidature->id }} })"><i data-lucide="{{ $candidature->photo ? 'image' : 'image-plus' }}"></i>{{ $candidature->photo ? 'Modifier ma photo' : 'Ajouter ma photo' }}</button>
                        <button type="button" class="btn btn-danger-contour" wire:loading.attr="disabled"
                                x-on:click="window.demanderMotif({
                                    titre: 'Retirer votre candidature ?',
                                    texte: @js('Vous n’apparaîtrez plus sur la liste des candidates de « '.$candidature->scrutin->libelle.' » et ne pourrez recevoir aucune voix. Vous restez électrice et pouvez réactiver votre candidature jusqu’à l’ouverture du scrutin.'),
                                    bouton: 'Retirer ma candidature',
                                    annuler: 'Garder ma candidature',
                                }).then((reponse) => reponse.confirme && $wire.desister({{ $candidature->id }}, reponse.motif))">
                            <i data-lucide="user-x"></i>Retirer ma candidature
                        </button>
                        </div>
                    @else
                        <button type="button" class="btn btn-secondaire" wire:click="reactiver({{ $candidature->id }})" wire:loading.attr="disabled"><i data-lucide="user-check"></i>Réactiver ma candidature</button>
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
                        <a href="{{ route('exports.resultats', [$resultat, 'format' => 'pdf']) }}" wire:key="resultat-{{ $resultat->id }}">
                            <span>{{ $resultat->libelle }}<small>{{ $resultat->phase->libelle }}@if ($resultat->procesVerbal?->date_publication) · publié le {{ $resultat->procesVerbal->date_publication->timezone('Africa/Porto-Novo')->format('d/m/Y') }}@endif</small></span>
                            <span class="lien"><i data-lucide="file-down"></i>PDF</span>
                        </a>
                    @endforeach
                </div>
            @endif
        </section>
    </div>
</div>
