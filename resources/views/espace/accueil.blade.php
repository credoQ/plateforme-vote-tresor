<x-layouts.app titre="Accueil" :ariane="['Mon espace']" actif="espace">
    <div class="titre-page">
        <div>
            <h1>Bonjour {{ $utilisateur->name }}</h1>
            <p>
                {{ $utilisateur->roles->map(fn ($role) => $role->code->libelle())->implode(' · ') }}
                @if ($utilisateur->structure) · {{ $utilisateur->structure->libelle }} @endif
            </p>
        </div>
    </div>

    @if ($annonces->isNotEmpty())
        <div class="annonces" aria-label="Annonces">
            @foreach ($annonces as $annonce)
                @if ($annonce->statut === \App\Enums\Scrutins\StatutScrutin::Ouvert)
                    <div class="alerte alerte--info"><i data-lucide="megaphone"></i><div><strong>Vote ouvert</strong> · {{ $annonce->libelle }}, jusqu’au {{ $annonce->date_heure_fermeture?->timezone('Africa/Porto-Novo')->format('d/m/Y à H:i') }}.</div></div>
                @else
                    <div class="alerte alerte--succes"><i data-lucide="trophy"></i><div><strong>Résultats publiés</strong> · {{ $annonce->libelle }}. <a class="lien" href="{{ route('exports.resultats', [$annonce, 'format' => 'pdf']) }}">Télécharger les résultats (PDF)</a></div></div>
                @endif
            @endforeach
        </div>
    @endif

    <div class="grille-tuiles espace-bloc">
        <div class="tuile">
            <div class="tuile__haut"><span class="surtitre">Identifiant</span><span class="tuile__icone"><i data-lucide="id-card"></i></span></div>
            <div class="tuile__valeur">{{ $utilisateur->login }}</div>
            <div class="tuile__detail">Dernière connexion : {{ $utilisateur->derniere_connexion?->timezone('Africa/Porto-Novo')->format('d/m/Y à H:i') ?? 'aucune' }}</div>
        </div>
        @if ($affectation)
            <div class="tuile">
                <div class="tuile__haut"><span class="surtitre">Affectation</span><span class="tuile__icone"><i data-lucide="map-pin"></i></span></div>
                <div class="tuile__valeur">{{ $affectation->structure->code }}</div>
                <div class="tuile__detail">{{ $affectation->structure->libelle }}, depuis le {{ $affectation->date_debut->format('d/m/Y') }}</div>
            </div>
        @endif
    </div>

    @can('voter')
        <div class="carte">
            <div class="carte__entete"><h2>Mon scrutin</h2></div>
            <div class="carte__corps">
                @if ($inscription)
                    <p><strong>{{ $inscription->scrutin->libelle }}</strong> · {{ $inscription->scrutin->phase->libelle }}</p>
                    <p class="aide">
                        @if ($inscription->a_vote)
                            Vous avez voté. Accusé n° {{ $inscription->numero_accuse }}.
                        @elseif ($inscription->scrutin->date_heure_ouverture)
                            Vote du {{ $inscription->scrutin->date_heure_ouverture->timezone('Africa/Porto-Novo')->format('d/m/Y H:i') }} au {{ $inscription->scrutin->date_heure_fermeture->timezone('Africa/Porto-Novo')->format('d/m/Y H:i') }}.
                        @endif
                    </p>
                    @unless ($inscription->a_vote)
                        <a href="{{ route('vote') }}" class="btn btn-primaire"><i data-lucide="vote"></i>Accéder au bulletin</a>
                    @endunless
                @else
                    <div class="etat-vide"><i data-lucide="calendar-clock"></i><p>Aucun scrutin ne vous concerne pour la phase en cours.</p></div>
                @endif
            </div>
        </div>
    @endcan

    @foreach ($candidatures as $candidature)
        <section class="carte espace-bloc">
            <div class="carte__entete"><h2>Ma candidature</h2></div>
            <div class="carte__corps candidature">
                <div>
                    <p><strong>{{ $candidature->scrutin->libelle }}</strong> · {{ $candidature->scrutin->phase->libelle }}</p>
                    @if ($candidature->estActive())
                        <p class="aide">{{ $candidature->origine === \App\Enums\Scrutins\OrigineCandidate::Automatique ? 'Candidate d’office' : 'Lauréate qualifiée' }}</p>
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
</x-layouts.app>
