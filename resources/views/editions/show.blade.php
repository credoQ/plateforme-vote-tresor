@use('App\Enums\Editions\StatutPhase')
@use('App\Enums\Scrutins\StatutScrutin')
<x-layouts.app :titre="'Édition '.$edition->annee" :ariane="['Pilotage', 'Éditions', (string) $edition->annee]" actif="editions">
    <div class="titre-page">
        <div>
            <div class="titre-page__surtitre"><span class="surtitre">Édition {{ $edition->annee }}</span><span class="badge {{ $edition->statut->badge() }}">{{ $edition->statut->libelle() }}</span></div>
            <h1>{{ $edition->libelle }}</h1>
            <p>Du {{ $edition->date_debut->format('d/m/Y') }} au {{ $edition->date_fin->format('d/m/Y') }} · {{ $edition->date_gel ? 'listes gelées au '.$edition->date_gel->format('d/m/Y') : 'listes non encore gelées' }}</p>
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
                    <a wire:navigate href="{{ route('editions.show', [$edition, 'phase' => $etape->numero]) }}" @if ($etape->is($phase)) aria-current="step" @endif>
                        <span class="frise-phases__puce">@if ($etape->statut === StatutPhase::Cloturee)<i data-lucide="check"></i>@else{{ $etape->numero }}@endif</span>
                        <span class="frise-phases__texte">
                            <span class="frise-phases__numero">Étape {{ $loop->iteration }} sur {{ $loop->count }}</span>
                            <strong>{{ $etape->libelle }}</strong>
                            <small>@if ($etape->date_debut)Du {{ $etape->date_debut->format('d/m/Y') }} au {{ $etape->date_fin->format('d/m/Y') }}@else Dates à planifier @endif</small>
                            <span class="badge {{ $etatsDesPhases[$etape->id]['badge'] }}">{{ $etatsDesPhases[$etape->id]['libelle'] }}</span>
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
                <span class="badge {{ $etatsDesPhases[$phase->id]['badge'] }}">{{ $etatsDesPhases[$phase->id]['libelle'] }}</span>
            </div>

            @if ($errors->has('listes'))
                <div class="carte__bandeau">
                    <div class="alerte alerte--erreur" role="alert"><i data-lucide="circle-alert"></i>
                        <div>
                            @foreach ($errors->get('listes') as $message)
                                <p>{{ $message }}</p>
                            @endforeach
                        </div>
                    </div>
                </div>
                {{-- Fenêtre d'erreur SweetAlert (resources/js/alertes.js) ; le détail des scrutins reste dans l'encadré. --}}
                <script>
                    (() => {
                        const afficher = () => window.signalerErreur?.(@js(count($errors->get('listes')) > 1 ? $errors->first('listes').' Le détail est affiché sur la phase.' : $errors->first('listes')));
                        document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', afficher) : afficher();
                    })();
                </script>
            @endif

            <ol class="taches-phase" aria-label="Étapes de la {{ $phase->libelle }}">
                @foreach ($taches as $tache)
                    <li @class(['taches-phase__tache', 'est-faite' => $tache['faite'], 'est-courante' => $tache['courante'], 'est-a-venir' => ! $tache['faite'] && ! $tache['courante']]) @if ($tache['courante']) aria-current="step" @endif>
                        <span class="taches-phase__puce"><i data-lucide="{{ $tache['icone'] }}"></i></span>
                        <div>
                            <div class="taches-phase__titre">{{ $tache['titre'] }}</div>
                            <div class="taches-phase__detail">{{ $tache['detail'] }}@if ($tache['action'] && $tache['motif']) {{ $tache['motif'] }}@endif</div>
                        </div>
                        <div class="taches-phase__action">
                            @if ($tache['action'] === 'planifier')
                                <button type="button" @class(['btn', 'btn-primaire' => $tache['courante'], 'btn-tertiaire btn--sm' => ! $tache['courante']]) x-data x-on:click="$dispatch('ouvrir-modale', 'planifier')"><i data-lucide="{{ $tache['faite'] ? 'pencil' : 'calendar-check' }}"></i>{{ $tache['libelleAction'] }}</button>
                            @elseif ($tache['action'] === 'generer')
                                <form method="POST" action="{{ route('phases.scrutins.generer', $phase) }}">
                                    @csrf
                                    <button type="submit" @class(['btn', 'btn-primaire' => $tache['courante'], 'btn-secondaire' => ! $tache['courante']])><i data-lucide="wand-sparkles"></i>{{ $tache['libelleAction'] }}</button>
                                </form>
                            @elseif ($tache['action'] === 'arreter')
                                <button type="button" @class(['btn', 'btn-primaire' => $tache['courante'], 'btn-secondaire' => ! $tache['courante']]) data-ouvrir="modale-arreter-listes-{{ $phase->id }}"><i data-lucide="lock"></i>{{ $tache['libelleAction'] }}</button>
                            @elseif ($tache['action'] === 'ouvrir')
                                <button type="button" @class(['btn', 'btn-primaire' => $tache['courante'], 'btn-secondaire' => ! $tache['courante']]) data-ouvrir="modale-ouvrir-{{ $phase->id }}" @disabled(! $tache['actionPossible'])><i data-lucide="play"></i>{{ $tache['libelleAction'] }}</button>
                            @elseif ($tache['action'] === 'cloturer')
                                <button type="button" @class(['btn', 'btn-primaire' => $tache['courante'], 'btn-secondaire' => ! $tache['courante']]) data-ouvrir="modale-cloturer-{{ $phase->id }}" @disabled(! $tache['actionPossible'])><i data-lucide="flag"></i>{{ $tache['libelleAction'] }}</button>
                            @elseif ($tache['faite'])
                                <span class="badge badge--succes">Fait</span>
                            @else
                                <span class="badge">À venir</span>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ol>

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
                <div><strong>{{ count(session('sans_femme')) }} zone(s) de vote sans scrutin</strong>, faute d’agent femme en service : {{ implode(', ', session('sans_femme')) }}. Leurs agents voteront aux phases 2 et 3.</div>
            </div>
        @endif
        @if (session('sans_agent') && $phase->scrutins_count > 0)
            <div class="alerte alerte--info espace-bloc"><i data-lucide="info"></i>
                <div><strong>{{ count(session('sans_agent')) }} zone(s) de vote sans scrutin</strong>, faute d’agent en service : {{ implode(', ', session('sans_agent')) }}.</div>
            </div>
        @endif
    @endif

    <x-slot:modales>
        @if ($phase && collect($taches)->contains('action', 'planifier'))
            <div class="voile" x-data="{ ouvert: @js($errors->has('phase') && old('date_debut') !== null) }" x-show="ouvert" x-cloak
                 x-on:ouvrir-modale.window="ouvert = $event.detail === 'planifier'" x-on:keydown.escape.window="ouvert = false" x-on:click.self="ouvert = false">
                <form method="POST" action="{{ route('phases.update', $phase) }}" class="modale" role="dialog" aria-modal="true" aria-labelledby="titre-planifier">
                    @csrf
                    @method('PUT')
                    <div class="modale__entete">
                        <span class="pastille-icone"><i data-lucide="calendar-range"></i></span>
                        <div>
                            <h2 id="titre-planifier">Planifier la {{ $phase->libelle }}</h2>
                            <p>Choisissez les dates de vote de la phase. Chaque scrutin se planifie ensuite dans ces dates.</p>
                        </div>
                        <button type="button" class="icone-bouton modale__fermer" aria-label="Fermer" x-on:click="ouvert = false"><i data-lucide="x"></i></button>
                    </div>
                    <div class="modale__corps formulaire formulaire--deux-colonnes">
                        <div class="formulaire__groupe">
                            <label class="libelle" for="planifier-debut">Premier jour <span class="requis">*</span></label>
                            <input id="planifier-debut" name="date_debut" type="date" class="champ" min="{{ $bornesPlanification['debut'] }}" max="{{ $bornesPlanification['fin'] }}" value="{{ old('date_debut', $phase->date_debut?->format('Y-m-d')) }}" required>
                        </div>
                        <div class="formulaire__groupe">
                            <label class="libelle" for="planifier-fin">Dernier jour <span class="requis">*</span></label>
                            <input id="planifier-fin" name="date_fin" type="date" class="champ" min="{{ $bornesPlanification['debut'] }}" max="{{ $bornesPlanification['fin'] }}" value="{{ old('date_fin', $phase->date_fin?->format('Y-m-d')) }}" required>
                        </div>
                        <p class="aide formulaire__groupe--pleine">{{ $bornesPlanification['consigne'] }}</p>
                        @if (old('date_debut') !== null)
                            @error('phase')<p class="erreur-champ formulaire__groupe--pleine">{{ $message }}</p>@enderror
                        @endif
                    </div>
                    <div class="modale__pied">
                        <button type="button" class="btn btn-secondaire" x-on:click="ouvert = false">Annuler</button>
                        <button type="submit" class="btn btn-primaire"><i data-lucide="calendar-check"></i>Enregistrer les dates</button>
                    </div>
                </form>
            </div>
        @endif
        @if ($edition->estModifiable())
            <x-modale-confirmation id="modale-lancer" :titre="'Lancer l’édition '.$edition->annee.' ?'" :action="route('editions.lancer', $edition)" bouton="Lancer l’édition" icone="rocket">
                L’édition passera en cours et ne pourra plus être modifiée. Toutes les phases doivent être planifiées, les scrutins de la première phase générés, et aucune autre édition ne doit être en cours.
            </x-modale-confirmation>
        @endif
        @if ($phase && $phase->scrutins_en_preparation_count > 0 && $phase->statut !== StatutPhase::Cloturee)
            <x-modale-confirmation :id="'modale-arreter-listes-'.$phase->id" :titre="'Arrêter les listes de la '.$phase->libelle.' ?'" :action="route('phases.arreter-listes', $phase)" bouton="Arrêter les listes" icone="lock">
                Les listes des {{ $phase->scrutins_en_preparation_count }} scrutin(s) en préparation sont actualisées une dernière fois, puis figées.
                @if ($edition->date_gel)
                    Elles se calculent d’après les affectations au {{ $edition->date_gel->format('d/m/Y') }}, date de gel de l’édition.
                @else
                    C’est le premier arrêt de l’édition : la date de gel est fixée à aujourd’hui, et toutes les listes de l’édition, phases suivantes comprises, se calculeront d’après les affectations à cette date.
                @endif
                Si un scrutin n’a pas d’électeur ou pas de candidate, aucune liste n’est arrêtée.
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
