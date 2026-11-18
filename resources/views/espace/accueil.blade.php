<x-layouts.app titre="Accueil" :ariane="['Mon espace']" actif="espace">
    <div class="titre-page">
        <div>
            <div class="surtitre">Mon espace</div>
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
                    <div class="alerte alerte--succes"><i data-lucide="trophy"></i><div><strong>Résultats publiés</strong> · {{ $annonce->libelle }}. <a class="lien" href="{{ route('exports.resultats', $annonce) }}">Télécharger les résultats</a></div></div>
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
                <div class="tuile__valeur">{{ $affectation->structure->sigle ?? $affectation->structure->code }}</div>
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
                @else
                    <div class="etat-vide"><i data-lucide="calendar-clock"></i><p>Aucun scrutin ne vous concerne pour la phase en cours.</p></div>
                @endif
            </div>
        </div>
    @endcan
</x-layouts.app>
