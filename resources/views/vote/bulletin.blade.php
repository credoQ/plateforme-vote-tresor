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

    <section class="carte">
        <form method="GET" action="{{ route('vote') }}" class="barre-filtres">
            <div class="champ-icone"><i data-lucide="search"></i><input name="recherche" class="champ" value="{{ $recherche }}" placeholder="Rechercher une candidate par nom" aria-label="Rechercher une candidate"></div>
            @if ($structures->count() > 1)
                <x-select-recherche compact soumettre>
                    <select name="structure" class="champ" aria-label="Filtrer par structure">
                        <option value="">Toutes les structures</option>
                        @foreach ($structures as $id => $libelle)
                            <option value="{{ $id }}" @selected($structureId === $id)>{{ $libelle }}</option>
                        @endforeach
                    </select>
                </x-select-recherche>
            @endif
            <button type="submit" class="btn btn-secondaire"><i data-lucide="filter"></i>Filtrer</button>
            <span class="aide"><b class="chiffres">{{ $candidates->count() }}</b> candidate(s) sur {{ $total }}</span>
        </form>

        <form method="POST" action="{{ route('vote.confirmer') }}">
            @csrf
            <div class="carte__corps">
                <fieldset class="grille-candidates">
                    <legend class="sr-only">Candidates du scrutin</legend>
                    @forelse ($candidates as $candidate)
                        <label class="carte-candidate">
                            <input type="radio" name="candidate" value="{{ $candidate['id'] }}" required @checked((string) old('candidate') === (string) $candidate['id'])>
                            <span class="carte-candidate__coche"><i data-lucide="check"></i></span>
                            <span class="avatar avatar--grand avatar--sans-anneau">{{ $candidate['initiales'] }}</span>
                            <span><span class="carte-candidate__nom">{{ $candidate['nom'] }}</span><br><span class="carte-candidate__structure">{{ $candidate['structure'] }}</span></span>
                        </label>
                    @empty
                        <div class="etat-vide"><i data-lucide="search-x"></i><p>Aucune candidate ne correspond à la recherche.</p></div>
                    @endforelse
                    @if ($blancAutorise)
                        <label class="carte-candidate">
                            <input type="radio" name="candidate" value="{{ \App\Http\Controllers\Vote\VoteController::BLANC }}" required>
                            <span class="carte-candidate__coche"><i data-lucide="check"></i></span>
                            <span class="avatar avatar--grand avatar--sans-anneau"><i data-lucide="file"></i></span>
                            <span><span class="carte-candidate__nom">Vote blanc</span><br><span class="carte-candidate__structure">Aucune candidate</span></span>
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
