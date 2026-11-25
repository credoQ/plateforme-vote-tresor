<x-layouts.vote titre="Voter" :contexte="$scrutin->phase->libelle.' · '.$scrutin->libelle" :etape="1">
    <div class="titre-page">
        <div>
            <h1>Choisissez votre candidate</h1>
            <p>{{ $scrutin->libelle }}</p>
        </div>
        <div class="titre-page__actions">
            <span class="badge badge--succes">Ouvert</span>
            <span class="etiquette"><i data-lucide="clock"></i>Clôture le {{ $scrutin->date_heure_fermeture->timezone('Africa/Porto-Novo')->format('d/m/Y à H:i') }}</span>
        </div>
    </div>

    <section class="carte" x-data="filtreBulletin">
        {{-- Filtre appliqué dans la page pendant la saisie : rien n'est rechargé, le choix coché est conservé. --}}
        <div class="barre-filtres" role="search">
            <div class="champ-icone"><i data-lucide="search"></i><input type="search" class="champ" x-model="recherche" placeholder="Rechercher une candidate par nom" aria-label="Rechercher une candidate"></div>
            @if ($structures->count() > 1)
                <x-select-recherche compact>
                    <select class="champ" x-model="structure" aria-label="Filtrer par structure">
                        <option value="">Toutes les structures</option>
                        @foreach ($structures as $id => $libelle)
                            <option value="{{ $id }}">{{ $libelle }}</option>
                        @endforeach
                    </select>
                </x-select-recherche>
            @endif
            <span class="aide" aria-live="polite"><b class="chiffres" x-text="nombreVisibles">{{ $total }}</b> candidate(s) sur {{ $total }}</span>
        </div>

        <form method="POST" action="{{ route('vote.confirmer') }}" novalidate data-choix-requis="candidate"
              @if ($blancAutorise)
                  data-choix-titre="Aucun choix" data-choix-texte="Choisissez une candidate, ou le vote blanc, avant de continuer."
              @else
                  data-choix-titre="Le vote blanc n’est pas autorisé" data-choix-texte="Pour ce scrutin, vous devez choisir une candidate avant de continuer."
              @endif
        >
            @csrf
            <div class="carte__corps">
                <fieldset class="grille-candidates grille-candidates--photos">
                    <legend class="sr-only">Candidates du scrutin</legend>
                    @foreach ($candidates as $candidate)
                        <label class="carte-candidate carte-candidate--photo" data-candidate data-nom="{{ $candidate['nom'] }}" data-structure="{{ $candidate['structure_id'] }}" x-show="correspond($el)">
                            <input type="radio" name="candidate" value="{{ $candidate['id'] }}" required @checked((string) old('candidate') === (string) $candidate['id'])>
                            <span class="carte-candidate__coche"><i data-lucide="check"></i></span>
                            <span class="carte-photo">
                                @if ($candidate['photo'])
                                    <img class="carte-photo__image" src="{{ $candidate['photo'] }}" alt="" loading="lazy">
                                @else
                                    <span class="carte-photo__initiales" aria-hidden="true">{{ $candidate['initiales'] }}</span>
                                @endif
                                <span class="carte-photo__legende"><span class="carte-candidate__nom">{{ $candidate['nom'] }}</span><span class="carte-candidate__structure">{{ $candidate['structure'] }}</span></span>
                            </span>
                        </label>
                    @endforeach
                    <div class="etat-vide" x-show="nombreVisibles === 0" x-cloak><i data-lucide="search-x"></i><p>Aucune candidate ne correspond à la recherche.</p></div>
                    @if ($blancAutorise)
                        <label class="carte-candidate carte-candidate--photo">
                            <input type="radio" name="candidate" value="{{ \App\Http\Controllers\Vote\VoteController::BLANC }}" required>
                            <span class="carte-candidate__coche"><i data-lucide="check"></i></span>
                            <span class="carte-photo">
                                <span class="carte-photo__initiales" aria-hidden="true"><i data-lucide="file"></i></span>
                                <span class="carte-photo__legende"><span class="carte-candidate__nom">Vote blanc</span><span class="carte-candidate__structure">Aucune candidate</span></span>
                            </span>
                        </label>
                    @endif
                </fieldset>
            </div>

            <div class="barre-vote">
                <div><div class="surtitre">Étape 1 sur 2</div><b>Sélectionnez votre candidate</b></div>
                <div class="barre-vote__actions">
                    <a href="{{ route('espace') }}" class="btn btn-discret">Plus tard</a>
                    <button type="submit" class="btn btn-primaire btn--lg"><i data-lucide="vote"></i>Continuer vers la confirmation</button>
                </div>
            </div>
        </form>
    </section>
</x-layouts.vote>
