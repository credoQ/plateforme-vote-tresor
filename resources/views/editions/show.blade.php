@use('App\Enums\Editions\StatutEdition')
@use('App\Enums\Editions\StatutPhase')
@use('App\Enums\Scrutins\StatutScrutin')
<x-layouts.app :titre="'Édition '.$edition->annee" :ariane="['Pilotage', 'Éditions', (string) $edition->annee]" actif="editions">
    <div class="titre-page">
        <div>
            <div class="titre-page__surtitre"><span class="surtitre">Édition {{ $edition->annee }}</span><span class="badge {{ $edition->statut->badge() }}">{{ $edition->statut->libelle() }}</span></div>
            <h1>{{ $edition->libelle }}</h1>
            <p>Du {{ $edition->date_debut->format('d/m/Y') }} au {{ $edition->date_fin->format('d/m/Y') }} · listes gelées au {{ $edition->date_gel->format('d/m/Y') }}</p>
        </div>
        <div class="titre-page__actions">
            @if ($edition->estModifiable())
                <a wire:navigate href="{{ route('editions.edit', $edition) }}" class="btn btn-secondaire"><i data-lucide="settings"></i>Paramétrer</a>
                <button type="button" class="btn btn-primaire" data-ouvrir="modale-lancer"><i data-lucide="rocket"></i>Lancer l’édition</button>
            @else
                <a wire:navigate href="{{ route('tableau-de-bord') }}" class="btn btn-secondaire"><i data-lucide="chart-line"></i>Tableau de bord</a>
            @endif
        </div>
    </div>

    <ul class="pastilles espace-bloc" aria-label="Paramètres du vote">
        <li>Vote blanc <strong>{{ $edition->vote_blanc_autorise ? 'Autorisé' : 'Non' }}</strong></li>
        <li>Vote pour soi-même <strong>{{ $edition->vote_pour_soi_autorise ? 'Autorisé' : 'Non' }}</strong></li>
        <li>Quorum <strong>{{ $edition->quorum === null ? 'Aucun' : number_format((float) $edition->quorum, 0, ',', ' ').' %' }}</strong></li>
        <li>Rééligibilité <strong>{{ $edition->delai_reeligibilite }} an(s)</strong></li>
    </ul>

    <nav class="carte frise-phases espace-bloc" aria-label="Phases de l’édition">
        <ol>
            @foreach ($edition->phases as $etape)
                <li @class(['frise-phases__etape', 'est-choisie' => $etape->is($phase), 'est-faite' => $etape->statut === StatutPhase::Cloturee])>
                    <a wire:navigate href="{{ route('editions.show', [$edition, 'phase' => $etape->numero]) }}" @if ($etape->is($phase)) aria-current="page" @endif>
                        <span class="frise-phases__puce">@if ($etape->statut === StatutPhase::Cloturee)<i data-lucide="check"></i>@else{{ $etape->numero }}@endif</span>
                        <span class="frise-phases__texte">
                            <strong>{{ $etape->libelle }}</strong>
                            <small>@if ($etape->date_debut)Du {{ $etape->date_debut->format('d/m/Y') }} au {{ $etape->date_fin->format('d/m/Y') }}@else À planifier @endif</small>
                            <span class="badge {{ $etape->statut->badge() }}">{{ $etape->statut->libelle() }}</span>
                        </span>
                    </a>
                </li>
            @endforeach
        </ol>
    </nav>

    @if ($phase)
        <section class="carte carte--choisie" id="phase-{{ $phase->numero }}">
            <div class="carte__entete">
                <span class="organisation-phase__numero">{{ $phase->numero }}</span>
                <h2>{{ $phase->libelle }}</h2>
                <span class="badge {{ $phase->statut->badge() }}">{{ $phase->statut->libelle() }}</span>
                <div class="carte__entete-fin">
                    @if ($phase->scrutins_count > 0)
                        <span class="aide">{{ $phase->scrutins_termines_count }} scrutin(s) terminé(s) sur {{ $phase->scrutins_count }}</span>
                    @endif
                    @if ($edition->statut === StatutEdition::EnCours)
                        @if ($phase->statut === StatutPhase::Planifiee)
                            <button type="button" class="btn btn-primaire" data-ouvrir="modale-ouvrir-{{ $phase->id }}"><i data-lucide="play"></i>Ouvrir la phase</button>
                        @elseif ($phase->statut === StatutPhase::Ouverte)
                            <button type="button" class="btn btn-primaire" data-ouvrir="modale-cloturer-{{ $phase->id }}" @disabled($clotureImpossible) @if ($clotureImpossible) title="Tous les résultats doivent être publiés" @endif><i data-lucide="flag"></i>Clôturer la phase</button>
                        @endif
                    @endif
                </div>
            </div>

            @if ($phase->statut === StatutPhase::Planifiee && $edition->statut !== StatutEdition::Cloturee)
                <form method="POST" action="{{ route('phases.update', $phase) }}" class="formulaire-ligne carte__bandeau">
                    @csrf
                    @method('PUT')
                    <div class="formulaire__groupe">
                        <label class="libelle" for="debut-{{ $phase->id }}">Début</label>
                        <input id="debut-{{ $phase->id }}" name="date_debut" type="date" class="champ" value="{{ $phase->date_debut?->format('Y-m-d') }}" required>
                    </div>
                    <div class="formulaire__groupe">
                        <label class="libelle" for="fin-{{ $phase->id }}">Fin</label>
                        <input id="fin-{{ $phase->id }}" name="date_fin" type="date" class="champ" value="{{ $phase->date_fin?->format('Y-m-d') }}" required>
                    </div>
                    <button type="submit" class="btn btn-secondaire"><i data-lucide="calendar-check"></i>Planifier la phase</button>
                </form>
            @endif

            <nav class="onglets" aria-label="Rubriques de la phase">
                <a wire:navigate href="{{ route('editions.show', [$edition, 'phase' => $phase->numero]) }}" @class(['onglet', 'est-actif' => $onglet === 'scrutins']) @if ($onglet === 'scrutins') aria-current="page" @endif>Scrutins <span class="compteur">{{ $phase->scrutins_count }}</span></a>
                <a wire:navigate href="{{ route('editions.show', [$edition, 'phase' => $phase->numero, 'onglet' => 'colleges']) }}" @class(['onglet', 'est-actif' => $onglet === 'colleges']) @if ($onglet === 'colleges') aria-current="page" @endif>Périmètres <span class="compteur">{{ $phase->colleges->count() }}</span></a>
                <a wire:navigate href="{{ route('editions.show', [$edition, 'phase' => $phase->numero, 'onglet' => 'resultats']) }}" @class(['onglet', 'est-actif' => $onglet === 'resultats']) @if ($onglet === 'resultats') aria-current="page" @endif>Résultats à valider <span @class(['compteur', 'compteur--attente' => $phase->scrutins_a_valider_count > 0])>{{ $phase->scrutins_a_valider_count }}</span></a>
            </nav>

            @if ($onglet === 'colleges')
                <div class="carte__corps">
                    <ul class="colleges-phase" aria-label="Périmètres de la phase">
                        @foreach ($phase->colleges as $collegeDeLaPhase)
                            <li><i data-lucide="users-round"></i>{{ $collegeDeLaPhase->resume() }}</li>
                        @endforeach
                    </ul>
                </div>
            @elseif ($phase->scrutins_count === 0)
                <div class="etat-vide">
                    <i data-lucide="clipboard-list"></i>
                    <p>Les scrutins de cette phase ne sont pas encore générés.</p>
                    @if ($phase->statut === StatutPhase::Planifiee)
                        <form method="POST" action="{{ route('phases.scrutins.generer', $phase) }}">
                            @csrf
                            <button type="submit" class="btn btn-secondaire"><i data-lucide="wand-sparkles"></i>Générer les scrutins</button>
                        </form>
                    @endif
                </div>
            @else
                <x-barre-filtres :total="$scrutins->total()" libelle="scrutin(s)">
                    <input type="hidden" name="phase" value="{{ $phase->numero }}">
                    @if ($onglet !== 'scrutins')<input type="hidden" name="onglet" value="{{ $onglet }}">@endif
                    <x-filtre libelle="Recherche" pour="filtre-recherche" large>
                        <div class="champ-icone"><i data-lucide="search"></i><input id="filtre-recherche" type="search" name="recherche" class="champ" value="{{ $recherche }}" placeholder="Scrutin ou code"></div>
                    </x-filtre>
                    <x-filtre libelle="Périmètre" pour="filtre-college">
                        <x-select-recherche compact soumettre>
                            <select id="filtre-college" name="college" class="champ">
                                <option value="">Tous les périmètres</option>
                                @foreach ($colleges as $valeur => $libelle)
                                    <option value="{{ $valeur }}" @selected($college === $valeur)>{{ $libelle }}</option>
                                @endforeach
                            </select>
                        </x-select-recherche>
                    </x-filtre>
                    @if ($onglet === 'scrutins')
                        <x-filtre libelle="Statut" pour="filtre-statut">
                            <x-select-recherche compact soumettre>
                                <select id="filtre-statut" name="statut" class="champ">
                                    <option value="">Tous les statuts</option>
                                    @foreach (StatutScrutin::cases() as $cas)
                                        <option value="{{ $cas->value }}" @selected($statut === $cas)>{{ $cas->libelle() }}</option>
                                    @endforeach
                                </select>
                            </x-select-recherche>
                        </x-filtre>
                    @endif
                </x-barre-filtres>
                <div class="tableau-defilant">
                    <table class="tableau tableau--fiches">
                        <thead><tr><th>Scrutin</th><th class="nombre">Inscrits</th><th class="nombre">Candidates</th><th>Ouverture</th><th class="nombre">Participation</th><th>Statut</th><th class="actions"></th></tr></thead>
                        <tbody>
                            @forelse ($scrutins as $ligne)
                                <tr>
                                    <td>{{ $ligne['scrutin']->libelle }}<div class="aide">{{ $ligne['scrutin']->code }}</div></td>
                                    <td class="nombre">{{ number_format($ligne['scrutin']->inscriptions_count, 0, ',', ' ') }}</td>
                                    <td class="nombre">{{ $ligne['scrutin']->candidates_actives_count }}</td>
                                    <td>{{ $ligne['scrutin']->date_heure_ouverture?->timezone('Africa/Porto-Novo')->format('d/m/Y H:i') ?? 'À planifier' }}</td>
                                    <td class="nombre">{{ $ligne['taux'] === null ? '-' : number_format($ligne['taux'], 1, ',', ' ').' %' }}</td>
                                    <td><span class="badge {{ $ligne['scrutin']->statut->badge() }}">{{ $ligne['scrutin']->statut->libelle() }}</span></td>
                                    <td class="actions">
                                        @if ($onglet === 'resultats')
                                            <a href="{{ route('scrutins.show', $ligne['scrutin']) }}#resultats" class="btn btn-tertiaire btn--sm"><i data-lucide="badge-check"></i>Valider</a>
                                        @else
                                            <a wire:navigate href="{{ route('scrutins.show', $ligne['scrutin']) }}" class="btn btn-tertiaire btn--sm"><i data-lucide="eye"></i>Ouvrir</a>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7"><div class="etat-vide"><i data-lucide="clipboard-list"></i><p>{{ $onglet === 'resultats' ? 'Aucun résultat à valider.' : 'Aucun scrutin ne correspond.' }}</p></div></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                {{ $scrutins->onEachSide(1)->links('pagination.tresor', ['libelle' => 'scrutins']) }}
            @endif
        </section>

        @if (session('sans_femme') && $phase->numero === 1 && $phase->scrutins_count > 0)
            <div class="alerte alerte--info espace-bloc"><i data-lucide="info"></i>
                <div><strong>{{ count(session('sans_femme')) }} zone(s) de vote sans scrutin</strong>, faute d’agent femme en service à la date de gel : {{ implode(', ', session('sans_femme')) }}. Leurs agents voteront aux phases 2 et 3.</div>
            </div>
        @endif
    @endif

    <x-slot:modales>
        @if ($edition->estModifiable())
            <x-modale-confirmation id="modale-lancer" :titre="'Lancer l’édition '.$edition->annee.' ?'" :action="route('editions.lancer', $edition)" bouton="Lancer l’édition" icone="rocket">
                L’édition passera en cours et ne pourra plus être modifiée. Toutes les phases doivent être planifiées, les scrutins de la première phase générés, et aucune autre édition ne doit être en cours.
            </x-modale-confirmation>
        @endif
        @foreach ($edition->phases as $etape)
            @if ($etape->statut === StatutPhase::Planifiee)
                <x-modale-confirmation :id="'modale-ouvrir-'.$etape->id" :titre="'Ouvrir la '.$etape->libelle.' ?'" :action="route('phases.ouvrir', $etape)" bouton="Ouvrir la phase" icone="play">
                    Chaque scrutin s’ouvrira ensuite automatiquement à sa date-heure d’ouverture. La phase précédente doit être clôturée.
                </x-modale-confirmation>
            @elseif ($etape->statut === StatutPhase::Ouverte)
                <x-modale-confirmation :id="'modale-cloturer-'.$etape->id" :titre="'Clôturer la '.$etape->libelle.' ?'" :action="route('phases.cloturer', $etape)" bouton="Clôturer la phase" icone="flag">
                    Tous les résultats doivent être validés et publiés. Les lauréates seront alors reprises dans la phase suivante.
                </x-modale-confirmation>
            @endif
        @endforeach
    </x-slot:modales>
</x-layouts.app>
