<x-dynamic-component :component="$gabarit" titre="Résultats" :ariane="['Mon espace', 'Résultats']" actif="palmares">
    <div class="titre-page">
        <div>
            <div class="surtitre">{{ $edition?->libelle ?? 'Résultats' }}</div>
            <h1>Palmarès</h1>
        </div>
    </div>

    @if ($palmares === null)
        <div class="carte"><div class="carte__corps"><div class="etat-vide"><i data-lucide="trophy"></i><p>Aucune édition n’est encore organisée.</p></div></div></div>
    @else
        @if ($palmares['femmes_modeles']->isNotEmpty())
            <div class="grille-tuiles espace-bloc">
                @foreach ($palmares['femmes_modeles'] as $femme)
                    <div class="tuile tuile--heros">
                        <div class="tuile__haut"><span class="surtitre">Femme modèle du Trésor {{ $edition->annee }}</span><span class="tuile__icone"><i data-lucide="crown"></i></span></div>
                        <div class="tuile__valeur">{{ $femme->agent->nomComplet() }}</div>
                        <div class="tuile__detail">{{ $femme->scrutin->libelle }} · {{ $femme->nb_voix }} voix</div>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="carte espace-bloc">
            <x-barre-filtres :action="route('palmares')">
                <x-filtre libelle="Recherche" pour="filtre-recherche" large>
                    <div class="champ-icone"><i data-lucide="search"></i><input id="filtre-recherche" type="search" name="recherche" class="champ" value="{{ $recherche }}" placeholder="Lauréate, matricule ou scrutin"></div>
                </x-filtre>
                <x-filtre libelle="Édition" pour="filtre-annee">
                    <x-select-recherche compact soumettre>
                        <select id="filtre-annee" name="annee" class="champ">
                            @foreach ($annees as $annee)
                                <option value="{{ $annee }}" @selected($edition->annee === $annee)>{{ $annee }}</option>
                            @endforeach
                        </select>
                    </x-select-recherche>
                </x-filtre>
                <x-filtre libelle="Phase" pour="filtre-phase">
                    <x-select-recherche compact soumettre>
                        <select id="filtre-phase" name="phase" class="champ">
                            <option value="">Toutes les phases</option>
                            @foreach ($phases as $choix)
                                <option value="{{ $choix->numero }}" @selected($phase === $choix->numero)>{{ $choix->libelle }}</option>
                            @endforeach
                        </select>
                    </x-select-recherche>
                </x-filtre>
            </x-barre-filtres>
        </div>

        @foreach ($palmares['phases'] as $phaseDuPalmares)
            <section class="carte espace-bloc">
                <div class="carte__entete"><h2>{{ $phaseDuPalmares['phase'] }}</h2></div>
                <div class="tableau-defilant">
                    <table class="tableau tableau--fiches">
                        <thead><tr><th>Scrutin</th><th>Lauréate</th><th>Matricule</th><th class="nombre">Voix</th></tr></thead>
                        <tbody>
                            @forelse ($phaseDuPalmares['laureates'] as $laureate)
                                <tr>
                                    <td>{{ $laureate['scrutin'] }}</td>
                                    <td><span class="badge badge--accent">Lauréate</span> {{ $laureate['agent'] }}</td>
                                    <td>{{ $laureate['matricule'] }}</td>
                                    <td class="nombre">{{ $laureate['voix'] }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4"><div class="etat-vide"><p>{{ $recherche !== '' ? 'Aucune lauréate ne correspond à la recherche.' : 'Aucun résultat publié pour cette phase.' }}</p></div></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        @endforeach
    @endif
</x-dynamic-component>
