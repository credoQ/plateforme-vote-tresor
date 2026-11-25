@use('App\Enums\Scrutins\StatutScrutin')
{{-- Tableau de bord (variante A, maquette « synthèse ») : l'essentiel tient sur un écran ; le tableau complet des
     scrutins s'ouvre dans la fenêtre « Suivi des scrutins », le détail d'un scrutin dans une petite fenêtre.
     Les données sont dans l'îlot « donnees », en deux parties liées par leur nom : seul cet îlot est rafraîchi chaque
     minute, et il est recalculé quand un filtre, la phase ou l'édition change (always). Un îlot ne peut pas être placé
     dans un @if : les conditions sont à l'intérieur. L'état des fenêtres (Alpine) est hors des îlots et survit aux
     rafraîchissements. --}}
<div x-data="{ suivi: false, vue: 'scrutins', detail: null }" x-on:keydown.escape.window="detail ? detail = null : suivi = false">
    <div class="titre-page">
        <div>
            <h1>Tableau de bord</h1>
            @if ($restreint)<p>Données limitées à votre structure.</p>@endif
        </div>
        <div class="titre-page__actions">
            @if ($editions !== [])
                <x-select-recherche compact wire:ignore>
                    <select class="champ" wire:model.live="annee" aria-label="Édition affichée">
                        @foreach ($editions as $annee => $libelle)
                            <option value="{{ $annee }}" @selected($edition?->annee === $annee)>{{ $libelle }}</option>
                        @endforeach
                    </select>
                </x-select-recherche>
            @endif
            @if ($edition && $edition->phases->count() > 1)
                <nav class="segment" aria-label="Phase affichée">
                    @foreach ($edition->phases as $choix)
                        <button type="button" wire:click="choisirPhase({{ $choix->numero }})" @class(['segment__lien', 'est-actif' => $choix->is($phaseAffichee)]) @if ($choix->is($phaseAffichee)) aria-current="true" @endif>{{ $choix->libelle }}</button>
                    @endforeach
                </nav>
            @endif
        </div>
    </div>

    @foreach ($alertesOuverture as $alerte)
        <div class="alerte alerte--{{ $alerte['ton'] }} espace-bloc" role="status">
            <i data-lucide="{{ $alerte['ton'] === 'erreur' ? 'circle-alert' : 'alarm-clock' }}"></i>
            <div>{{ $alerte['texte'] }} <a wire:navigate href="{{ route('editions.show', [$edition, 'phase' => $alerte['phase']->numero]) }}">Voir la phase</a></div>
        </div>
    @endforeach

    @island(name: 'donnees', always: true)
        <div wire:poll.60s>
            @if ($this->donnees === null)
                <div class="carte"><div class="carte__corps"><div class="etat-vide"><i data-lucide="calendar-x"></i><p>Aucune édition n’est encore créée.</p></div></div></div>
            @else
                <p class="horodatage espace-bloc"><i data-lucide="refresh-cw"></i>Mis à jour à {{ $this->donnees['misAJourLe']->format('H:i') }} · actualisation chaque minute</p>

                <div class="grille-tuiles">
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

                <div class="grille-trois espace-bloc">
                    <section class="carte">
                        <div class="carte__entete"><h2>Participation</h2></div>
                        <div class="carte__corps donut-bloc">
                            <x-donut :segments="[
                                ['libelle' => 'Votants', 'valeur' => $this->donnees['global']['votants'], 'couleur' => 1],
                                ['libelle' => 'Pas encore votés', 'valeur' => $this->donnees['global']['inscrits'] - $this->donnees['global']['votants'], 'couleur' => 0],
                            ]" :valeur="number_format($this->donnees['global']['taux'], 1, ',', ' ').' %'" legende="votants" />
                            <ul class="legende-donut">
                                <li><i class="pastille-graphe pastille-graphe--1"></i>Votants<b>{{ number_format($this->donnees['global']['votants'], 0, ',', ' ') }}</b></li>
                                <li><i class="pastille-graphe"></i>Pas encore votés<b>{{ number_format($this->donnees['global']['inscrits'] - $this->donnees['global']['votants'], 0, ',', ' ') }}</b></li>
                            </ul>
                        </div>
                    </section>
                    <section class="carte">
                        <div class="carte__entete"><h2>Scrutins par statut</h2></div>
                        <div class="carte__corps donut-bloc">
                            <x-donut :segments="$this->donnees['statuts']" :valeur="(string) $this->donnees['indicateurs']['total']" legende="scrutins" />
                            <ul class="legende-donut">
                                @foreach ($this->donnees['statuts'] as $segment)
                                    <li><i class="pastille-graphe pastille-graphe--{{ $segment['couleur'] }}"></i>{{ $segment['libelle'] }}<b>{{ $segment['valeur'] }}</b></li>
                                @endforeach
                            </ul>
                        </div>
                    </section>
                    <section class="carte">
                        <div class="carte__entete"><h2>Votants par pôle</h2></div>
                        <div class="carte__corps donut-bloc">
                            <x-donut :segments="array_map(fn ($pole) => ['libelle' => $pole['libelle'], 'valeur' => $pole['votants'], 'couleur' => $pole['couleur']], $this->donnees['poles'])" :valeur="number_format($this->donnees['global']['votants'], 0, ',', ' ')" legende="votants" />
                            <ul class="legende-donut">
                                @forelse ($this->donnees['poles'] as $pole)
                                    <li><i class="pastille-graphe pastille-graphe--{{ $pole['couleur'] }}"></i>{{ $pole['libelle'] }}<b>{{ number_format($pole['taux'], 1, ',', ' ') }} %</b></li>
                                @empty
                                    <li>Aucun scrutin</li>
                                @endforelse
                            </ul>
                        </div>
                    </section>
                </div>

                <div class="grille-large-etroite espace-bloc">
                    <section class="carte">
                        <div class="carte__entete"><h2>Participation par département</h2></div>
                        <div class="carte__corps barres-colonnes">
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
                    <section class="carte">
                        <div class="carte__entete">
                            <h2>Votes par heure</h2>
                            @if ($this->donnees['heures']['jour'])<span class="aide carte__aide">{{ $this->donnees['heures']['jour']->format('d/m/Y') }}</span>@endif
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
                </div>

                <section class="carte">
                    <div class="carte__entete">
                        <h2>Scrutins ouverts</h2><span class="etiquette">{{ $this->donnees['nombreOuverts'] }}</span>
                        <div class="carte__entete-fin">
                            <button type="button" class="btn btn-secondaire btn--sm" x-on:click="suivi = true"><i data-lucide="table-2"></i>Suivi des {{ $this->donnees['indicateurs']['total'] }} scrutins</button>
                        </div>
                    </div>
                    <div class="tableau-defilant">
                        <table class="tableau tableau--fiches">
                            <thead><tr><th>Scrutin</th><th class="nombre">Votants</th><th>Participation</th><th>Fermeture</th></tr></thead>
                            <tbody>
                                @forelse ($this->donnees['ouverts'] as $ligne)
                                    <tr class="est-cliquable" wire:key="ouvert-{{ $ligne['scrutin']->id }}" x-on:click="detail = @js($ligne['detail'])">
                                        <td>{{ $ligne['scrutin']->libelle }}<div class="aide">{{ $ligne['scrutin']->code }}</div></td>
                                        <td class="nombre">{{ number_format($ligne['votants'], 0, ',', ' ') }} / {{ number_format($ligne['inscrits'], 0, ',', ' ') }}</td>
                                        <td><div class="cellule-progression"><div class="progression"><span style="width:{{ $ligne['taux'] }}%"></span></div><b>{{ number_format($ligne['taux'], 1, ',', ' ') }} %</b></div></td>
                                        <td>{{ $ligne['scrutin']->date_heure_fermeture?->timezone('Africa/Porto-Novo')->format('d/m/Y à H:i') ?? '-' }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4"><div class="etat-vide"><i data-lucide="vote"></i><p>Aucun scrutin ouvert.</p></div></td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif
        </div>
    @endisland

    {{-- Fenêtre « Suivi des scrutins » : tableau complet des scrutins, ses filtres, et la participation par structure. --}}
    <div class="voile" x-show="suivi" x-cloak x-on:click.self="suivi = false">
        <div class="modale modale--large" role="dialog" aria-modal="true" aria-labelledby="titre-suivi">
            <div class="modale__entete">
                <h2 id="titre-suivi">Suivi des scrutins{{ $phaseAffichee ? ' · '.$phaseAffichee->libelle : '' }}</h2>
                <button type="button" class="icone-bouton modale__fermer" aria-label="Fermer" x-on:click="suivi = false"><i data-lucide="x"></i></button>
            </div>
            <div class="barre-filtres">
                <nav class="segment" aria-label="Vue">
                    <button type="button" class="segment__lien" x-bind:class="{ 'est-actif': vue === 'scrutins' }" x-on:click="vue = 'scrutins'">Scrutins</button>
                    <button type="button" class="segment__lien" x-bind:class="{ 'est-actif': vue === 'structures' }" x-on:click="vue = 'structures'">Structures</button>
                </nav>
                <x-filtre libelle="Recherche" pour="filtre-recherche" large x-show="vue === 'scrutins'">
                    <div class="champ-icone"><i data-lucide="search"></i><input id="filtre-recherche" type="search" class="champ" wire:model.live.debounce.400ms="recherche" placeholder="Scrutin ou code"></div>
                </x-filtre>
                <x-filtre libelle="Pôle" pour="filtre-pole" x-show="vue === 'scrutins'">
                    <x-select-recherche compact wire:ignore>
                        <select id="filtre-pole" class="champ" wire:model.live="pole">
                            <option value="">Tous les pôles</option>
                            @foreach ($poles as $valeur => $libelle)
                                <option value="{{ $valeur }}" @selected($pole === $valeur)>{{ $libelle }}</option>
                            @endforeach
                        </select>
                    </x-select-recherche>
                </x-filtre>
                <x-filtre libelle="Statut" pour="filtre-statut" x-show="vue === 'scrutins'">
                    <x-select-recherche compact wire:ignore>
                        <select id="filtre-statut" class="champ" wire:model.live="statut">
                            <option value="">Tous les statuts</option>
                            @foreach (StatutScrutin::cases() as $cas)
                                <option value="{{ $cas->value }}" @selected($statut === $cas->value)>{{ $cas->libelle() }}</option>
                            @endforeach
                        </select>
                    </x-select-recherche>
                </x-filtre>
                <div x-show="vue === 'scrutins'"><x-fin-filtres :total="$totalScrutins" libelle="scrutin(s)" :actifs="$filtresActifs" :reinitialiser="route('tableau-de-bord')" /></div>
            </div>

            @foreach ($alertesOuverture as $alerte)
        <div class="alerte alerte--{{ $alerte['ton'] }} espace-bloc" role="status">
            <i data-lucide="{{ $alerte['ton'] === 'erreur' ? 'circle-alert' : 'alarm-clock' }}"></i>
            <div>{{ $alerte['texte'] }} <a wire:navigate href="{{ route('editions.show', [$edition, 'phase' => $alerte['phase']->numero]) }}">Voir la phase</a></div>
        </div>
    @endforeach

    @island(name: 'donnees', always: true)
                <div class="tableau-defilant">
                    @if ($this->donnees !== null)
                        <table class="tableau tableau--fiches" x-show="vue === 'scrutins'">
                            <thead><tr><th>Scrutin</th><th>Pôle</th><th class="nombre">Inscrits</th><th class="nombre">Votants</th><th>Participation</th><th>Statut</th><th class="actions">Exports</th></tr></thead>
                            <tbody>
                                @forelse ($this->donnees['scrutins'] as $ligne)
                                    <tr class="est-cliquable" wire:key="scrutin-{{ $ligne['scrutin']->id }}" x-on:click="if (! $event.target.closest('a')) detail = @js($ligne['detail'])">
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
                        <table class="tableau tableau--fiches" x-show="vue === 'structures'" x-cloak>
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
                    @endif
                </div>
                @if ($this->donnees !== null)
                    <div x-show="vue === 'scrutins'">{{ $this->donnees['scrutins']->onEachSide(1)->links('pagination.tresor-livewire', ['libelle' => 'scrutins']) }}</div>
                @endif
            @endisland
        </div>
    </div>

    {{-- Fenêtre « Détail d'un scrutin » : ses données viennent de la ligne cliquée, sans nouvelle requête. --}}
    <div class="voile" x-show="detail" x-cloak x-on:click.self="detail = null">
        <div class="modale" role="dialog" aria-modal="true" aria-labelledby="titre-detail">
            <div>
                <div class="modale__entete">
                    <div>
                        <h2 id="titre-detail" x-text="detail?.libelle"></h2>
                        <span class="badge" x-bind:class="detail?.badge" x-text="detail?.statut"></span>
                    </div>
                    <button type="button" class="icone-bouton modale__fermer" aria-label="Fermer" x-on:click="detail = null"><i data-lucide="x"></i></button>
                </div>
                <div class="modale__corps donut-bloc">
                    <div class="donut" role="img" x-bind:aria-label="'Participation : ' + (detail?.tauxAffiche ?? '')">
                        <svg viewBox="0 0 42 42" aria-hidden="true">
                            <circle class="donut__fond" cx="21" cy="21" r="15.915"></circle>
                            <circle class="donut__arc--1" cx="21" cy="21" r="15.915" x-bind:stroke-dasharray="(detail?.taux ?? 0) + ' ' + (100 - (detail?.taux ?? 0))" stroke-dashoffset="0"></circle>
                        </svg>
                        <div class="donut__centre"><strong x-text="detail?.tauxAffiche"></strong><small>participation</small></div>
                    </div>
                    <dl class="recapitulatif__liste">
                        <div><dt>Votants</dt><dd x-text="detail ? detail.votants + ' / ' + detail.inscrits : ''"></dd></div>
                        <div><dt>Candidates</dt><dd x-text="detail?.candidates"></dd></div>
                        <div><dt>Ouverture</dt><dd x-text="detail?.ouverture"></dd></div>
                        <div><dt>Fermeture</dt><dd x-text="detail?.fermeture"></dd></div>
                    </dl>
                </div>
                <div class="modale__pied">
                    <a class="btn btn-tertiaire" x-bind:href="detail?.listeElectorale"><i data-lucide="download"></i>Liste électorale</a>
                    <a class="btn btn-tertiaire" x-show="detail?.resultats" x-bind:href="detail?.resultats"><i data-lucide="trophy"></i>Résultats</a>
                    <a wire:navigate class="btn btn-primaire" x-bind:href="detail?.fiche"><i data-lucide="eye"></i>Ouvrir le scrutin</a>
                </div>
            </div>
        </div>
    </div>
</div>
