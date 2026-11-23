@use('App\Enums\Scrutins\StatutScrutin')
{{-- Tableau de bord. Les données (indicateurs, graphiques, tableaux) sont dans l'îlot « donnees », en deux parties
     liées par leur nom : seul cet îlot est rafraîchi chaque minute (titre et filtres ne sont pas recalculés), et il est
     aussi recalculé quand un filtre ou la phase change (always). Livewire suspend le rafraîchissement quand l'onglet est
     masqué. Un îlot ne peut pas être placé dans un @if : les conditions sont à l'intérieur. --}}
<div>
    <div class="titre-page">
        <div>
            <div class="surtitre">{{ $edition?->libelle ?? 'Aucune édition' }}</div>
            <h1>Tableau de bord</h1>
            @if ($restreint)<p>Données limitées à votre structure.</p>@endif
        </div>
        @if ($edition && $edition->phases->count() > 1)
            <nav class="segment titre-page__actions" aria-label="Phase affichée">
                @foreach ($edition->phases as $choix)
                    <button type="button" wire:click="choisirPhase({{ $choix->numero }})" @class(['segment__lien', 'est-actif' => $choix->is($phaseAffichee)]) @if ($choix->is($phaseAffichee)) aria-current="true" @endif>{{ $choix->libelle }}</button>
                @endforeach
            </nav>
        @endif
    </div>

    @island(name: 'donnees', always: true)
        <div wire:poll.60s>
            @if ($this->donnees === null)
                <div class="carte"><div class="carte__corps"><div class="etat-vide"><i data-lucide="calendar-x"></i><p>Aucune édition n’est encore créée.</p></div></div></div>
            @else
                <p class="aide espace-bloc">Mis à jour à {{ $this->donnees['misAJourLe']->format('H:i') }} · actualisation automatique chaque minute</p>
                <div class="grille-tuiles espace-bloc">
                    <div class="tuile tuile--heros">
                        <div class="tuile__haut"><span class="surtitre">Taux de participation</span><span class="tuile__icone"><i data-lucide="percent"></i></span></div>
                        <div class="tuile__valeur">{{ number_format($this->donnees['global']['taux'], 1, ',', ' ') }} %</div>
                        <div class="progression progression--heros"><span style="width:{{ $this->donnees['global']['taux'] }}%"></span></div>
                        <div class="tuile__detail">{{ number_format($this->donnees['global']['votants'], 0, ',', ' ') }} votants sur {{ number_format($this->donnees['global']['inscrits'], 0, ',', ' ') }} inscrits</div>
                    </div>
                    <div class="tuile">
                        <div class="tuile__haut"><span class="surtitre">Scrutins ouverts</span><span class="tuile__icone"><i data-lucide="vote"></i></span></div>
                        <div class="tuile__valeur">{{ $this->donnees['indicateurs']['ouverts'] }} <small>/ {{ $this->donnees['indicateurs']['total'] }}</small></div>
                        <div class="tuile__detail">{{ $this->donnees['indicateurs']['termines'] }} terminé(s) · {{ $this->donnees['indicateurs']['a_venir'] }} à venir</div>
                    </div>
                    <div class="tuile">
                        <div class="tuile__haut"><span class="surtitre">Candidates actives</span><span class="tuile__icone"><i data-lucide="users-round"></i></span></div>
                        <div class="tuile__valeur">{{ number_format($this->donnees['indicateurs']['candidates'], 0, ',', ' ') }}</div>
                        <div class="tuile__detail">{{ $this->donnees['indicateurs']['desistements'] }} désistement(s)</div>
                    </div>
                    <div class="tuile">
                        <div class="tuile__haut"><span class="surtitre">Fermeture</span><span class="tuile__icone"><i data-lucide="clock"></i></span></div>
                        <div class="tuile__valeur">{{ $this->donnees['indicateurs']['restant'] ?? '-' }}</div>
                        <div class="tuile__detail">
                            @if ($this->donnees['indicateurs']['fermeture'])
                                le {{ $this->donnees['indicateurs']['fermeture']->timezone('Africa/Porto-Novo')->format('d/m/Y à H:i') }}
                            @else
                                Aucun scrutin ouvert
                            @endif
                        </div>
                    </div>
                </div>

                <div class="grille-deux espace-bloc">
                    <section class="carte">
                        <div class="carte__entete">
                            <h2>Votes par heure</h2>
                            @if ($this->donnees['heures']['jour'])<span class="aide carte__aide">{{ $this->donnees['heures']['jour']->format('d/m/Y') }} · heure du Bénin</span>@endif
                        </div>
                        <div class="carte__corps">
                            @if ($this->donnees['heures']['barres'] === [])
                                <div class="etat-vide"><i data-lucide="chart-column"></i><p>Aucun vote enregistré pour cette phase.</p></div>
                            @else
                                <div class="graphe-colonnes" aria-hidden="true">
                                    @foreach ($this->donnees['heures']['barres'] as $barre)
                                        <div class="graphe-colonnes__colonne" data-infobulle="{{ $barre['plage'] }} : {{ number_format($barre['votes'], 0, ',', ' ') }} vote(s)">
                                            <span @class(['graphe-colonnes__barre', 'est-maximum' => $barre['maximum']]) style="height:{{ $barre['hauteur'] }}%"></span>
                                        </div>
                                    @endforeach
                                </div>
                                <div class="graphe-colonnes__axe" aria-hidden="true">
                                    @foreach ($this->donnees['heures']['barres'] as $barre)<span>{{ $barre['heure'] }}</span>@endforeach
                                </div>
                                <table class="sr-only">
                                    <caption>Votes par heure</caption>
                                    <thead><tr><th>Heure</th><th>Votes</th></tr></thead>
                                    <tbody>
                                        @foreach ($this->donnees['heures']['barres'] as $barre)<tr><td>{{ $barre['plage'] }}</td><td>{{ $barre['votes'] }}</td></tr>@endforeach
                                    </tbody>
                                </table>
                            @endif
                        </div>
                    </section>

                    <section class="carte">
                        <div class="carte__entete"><h2>Participation par département</h2></div>
                        <div class="carte__corps">
                            @forelse ($this->donnees['departements'] as $ligne)
                                <div class="barre-horizontale">
                                    <span>{{ $ligne['libelle'] }}</span>
                                    <span class="barre-horizontale__piste"><span style="width:{{ $ligne['taux'] }}%"></span></span>
                                    <b>{{ number_format($ligne['taux'], 1, ',', ' ') }} %</b>
                                </div>
                            @empty
                                <div class="etat-vide"><p>Aucun inscrit.</p></div>
                            @endforelse
                        </div>
                    </section>
                </div>
            @endif
        </div>
    @endisland

    @if ($edition)
        <section class="carte carte--haut">
            <div class="carte__entete"><h2>Suivi des scrutins</h2></div>
            <div class="barre-filtres">
                <x-filtre libelle="Recherche" pour="filtre-recherche" large>
                    <div class="champ-icone"><i data-lucide="search"></i><input id="filtre-recherche" type="search" class="champ" wire:model.live.debounce.400ms="recherche" placeholder="Scrutin ou code"></div>
                </x-filtre>
                <x-filtre libelle="Pôle" pour="filtre-pole">
                    <x-select-recherche compact wire:ignore>
                        <select id="filtre-pole" class="champ" wire:model.live="pole">
                            <option value="">Tous les pôles</option>
                            @foreach ($poles as $valeur => $libelle)
                                <option value="{{ $valeur }}" @selected($pole === $valeur)>{{ $libelle }}</option>
                            @endforeach
                        </select>
                    </x-select-recherche>
                </x-filtre>
                <x-filtre libelle="Statut" pour="filtre-statut">
                    <x-select-recherche compact wire:ignore>
                        <select id="filtre-statut" class="champ" wire:model.live="statut">
                            <option value="">Tous les statuts</option>
                            @foreach (StatutScrutin::cases() as $cas)
                                <option value="{{ $cas->value }}" @selected($statut === $cas->value)>{{ $cas->libelle() }}</option>
                            @endforeach
                        </select>
                    </x-select-recherche>
                </x-filtre>
                <x-fin-filtres :total="$totalScrutins" libelle="scrutin(s)" :actifs="$filtresActifs" :reinitialiser="route('tableau-de-bord')" />
            </div>
        </section>
    @endif

    @island(name: 'donnees', always: true)
        @if ($this->donnees !== null)
            <section class="carte carte--bas espace-bloc">
                <div class="tableau-defilant">
                    <table class="tableau tableau--fiches">
                        <thead><tr><th>Scrutin</th><th>Pôle</th><th class="nombre">Inscrits</th><th class="nombre">Votants</th><th>Participation</th><th>Statut</th><th class="actions">Exports</th></tr></thead>
                        <tbody>
                            @forelse ($this->donnees['scrutins'] as $ligne)
                                <tr wire:key="scrutin-{{ $ligne['scrutin']->id }}">
                                    <td><a wire:navigate class="lien" href="{{ route('scrutins.show', $ligne['scrutin']) }}">{{ $ligne['scrutin']->libelle }}</a><div class="aide">{{ $ligne['scrutin']->code }} · {{ $ligne['scrutin']->candidates_actives }} candidate(s)</div></td>
                                    <td>{{ $ligne['scrutin']->poleElectoral?->code->libelle() ?? 'Toute la DGTCP' }}</td>
                                    <td class="nombre">{{ number_format($ligne['inscrits'], 0, ',', ' ') }}</td>
                                    <td class="nombre">{{ number_format($ligne['votants'], 0, ',', ' ') }}</td>
                                    <td><div class="cellule-progression"><div class="progression"><span style="width:{{ $ligne['taux'] }}%"></span></div><b>{{ number_format($ligne['taux'], 1, ',', ' ') }} %</b></div></td>
                                    <td><span class="badge {{ $ligne['scrutin']->statut->badge() }}">{{ $ligne['scrutin']->statut->libelle() }}</span></td>
                                    <td class="actions">
                                        <a href="{{ route('exports.liste-electorale', [$ligne['scrutin'], 'format' => 'xlsx']) }}" class="btn btn-discret btn--icone" title="Exporter la liste électorale (Excel)" aria-label="Exporter la liste électorale (Excel)"><i data-lucide="download"></i></a>
                                        @if ($ligne['scrutin']->statut === StatutScrutin::ResultatsPublies)<a href="{{ route('exports.resultats', [$ligne['scrutin'], 'format' => 'xlsx']) }}" class="btn btn-discret btn--icone" title="Exporter les résultats (Excel)" aria-label="Exporter les résultats (Excel)"><i data-lucide="trophy"></i></a>@endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7"><div class="etat-vide"><i data-lucide="clipboard-list"></i><p>Aucun scrutin ne correspond.</p></div></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                {{ $this->donnees['scrutins']->onEachSide(1)->links('pagination.tresor-livewire', ['libelle' => 'scrutins']) }}
            </section>

            <section class="carte">
                <div class="carte__entete"><h2>Participation par structure</h2></div>
                <div class="tableau-defilant">
                    <table class="tableau tableau--fiches">
                        <thead><tr><th>Structure</th><th class="nombre">Inscrits</th><th>Participation</th></tr></thead>
                        <tbody>
                            @forelse ($this->donnees['structures'] as $ligne)
                                <tr>
                                    <td>{{ $ligne['code'] }}<div class="aide">{{ $ligne['libelle'] }}</div></td>
                                    <td class="nombre">{{ number_format($ligne['inscrits'], 0, ',', ' ') }}</td>
                                    <td><div class="cellule-progression"><div class="progression"><span style="width:{{ $ligne['taux'] }}%"></span></div><b>{{ number_format($ligne['taux'], 1, ',', ' ') }} %</b></div></td>
                                </tr>
                            @empty
                                <tr><td colspan="3"><div class="etat-vide"><p>Aucun inscrit.</p></div></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
    @endisland
</div>
