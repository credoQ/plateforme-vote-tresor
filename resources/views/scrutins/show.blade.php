@use('App\Enums\Scrutins\OrigineCandidate')
@use('App\Enums\Scrutins\StatutScrutin')
<x-layouts.app :titre="$scrutin->libelle" :ariane="['Pilotage', 'Édition '.$scrutin->phase->edition->annee, $scrutin->code]" :actif="$pilote ? 'editions' : 'tableau-de-bord'">
    <div class="titre-page">
        <div>
            <div class="surtitre">{{ $scrutin->phase->libelle }} · {{ $scrutin->poleElectoral?->code->libelle() ?? 'Toute la DGTCP' }}</div>
            <h1>{{ $scrutin->libelle }}</h1>
            <p>
                {{ $scrutin->statut->libelle() }}
                @if ($scrutin->date_heure_ouverture)
                    · du {{ $scrutin->date_heure_ouverture->timezone($fuseau)->format('d/m/Y H:i') }} au {{ $scrutin->date_heure_fermeture->timezone($fuseau)->format('d/m/Y H:i') }}
                @endif
                @if ($restreint) · données limitées à votre structure @endif
            </p>
        </div>
        <div class="titre-page__actions">
            <x-liens-export route="exports.liste-electorale" :parametres="[$scrutin]" libelle="Liste électorale :" />
            @if ($pilote)
                <a wire:navigate href="{{ route('editions.show', [$scrutin->phase->edition, 'phase' => $scrutin->phase->numero]) }}" class="btn btn-tertiaire"><i data-lucide="arrow-left"></i>Retour à l’édition</a>
            @endif
        </div>
    </div>

    <nav class="carte frise-phases espace-bloc" aria-label="Déroulement du scrutin">
        <ol>
            @foreach ($deroulement['etapes'] as $etape)
                <li @class(['frise-phases__etape', 'est-faite' => $etape['faite'], 'est-choisie' => $etape['courante']])>
                    <a href="#etape-courante" @if ($etape['courante']) aria-current="step" @endif>
                        <span class="frise-phases__puce">@if ($etape['faite'])<i data-lucide="check"></i>@else{{ $loop->iteration }}@endif</span>
                        <span class="frise-phases__texte">
                            <span class="frise-phases__numero">Étape {{ $loop->iteration }} sur {{ $loop->count }}</span>
                            <strong>{{ $etape['titre'] }}</strong>
                            <small>{{ $etape['detail'] }}</small>
                        </span>
                    </a>
                </li>
            @endforeach
        </ol>
    </nav>

    @if ($pilote)
        <section class="carte espace-bloc etape-courante" id="etape-courante">
            @switch($deroulement['courante'])
                @case('listes')
                    <div class="carte__entete"><span class="frise-phases__numero">Étape 1 sur 5</span><h2>Arrêter les listes électorales</h2></div>
                    <div class="etape-courante__corps">
                        <span class="pastille-icone"><i data-lucide="lock"></i></span>
                        <div class="etape-courante__texte"><strong>Les listes sont encore modifiables.</strong><p>Actualisez-les après la correction d’un agent. Elles s’arrêtent pour toute la phase, depuis la fiche de l’édition.</p></div>
                        <div class="etape-courante__actions">
                            <form method="POST" action="{{ route('scrutins.actualiser', $scrutin) }}">@csrf<button type="submit" class="btn btn-secondaire"><i data-lucide="refresh-cw"></i>Actualiser les listes</button></form>
                            <a wire:navigate href="{{ route('editions.show', [$scrutin->phase->edition, 'phase' => $scrutin->phase->numero]) }}" class="btn btn-primaire"><i data-lucide="lock"></i>Arrêter les listes de la phase</a>
                        </div>
                    </div>
                    @break
                @case('horaires')
                    <div class="carte__entete"><span class="frise-phases__numero">Étape 2 sur 5</span><h2>Planifier le scrutin</h2></div>
                    <div class="etape-courante__corps">
                        <span class="pastille-icone"><i data-lucide="calendar-clock"></i></span>
                        <div class="etape-courante__texte"><strong>Quand le vote s’ouvre-t-il et se ferme-t-il ?</strong><p>Le scrutin s’ouvrira et se fermera seul à ces heures, une fois la phase ouverte. {{ $consignePlanification }}</p></div>
                        <div class="etape-courante__actions"><button type="button" class="btn btn-primaire" x-data x-on:click="$dispatch('ouvrir-modale', 'planifier')"><i data-lucide="calendar-clock"></i>Planifier le scrutin</button></div>
                    </div>
                    @break
                @case('vote')
                    <div class="carte__entete"><span class="frise-phases__numero">Étape 3 sur 5</span><h2>{{ $scrutin->statut === StatutScrutin::Ouvert ? 'Vote en cours' : 'Ouverture du vote' }}</h2></div>
                    <div class="etape-courante__corps">
                        <span class="pastille-icone"><i data-lucide="vote"></i></span>
                        @if ($scrutin->statut === StatutScrutin::Ouvert)
                            <div class="etape-courante__texte">
                                <strong>{{ $scrutin->fermetureDepassee() ? 'L’heure de fermeture est passée.' : 'Le vote se ferme le '.$scrutin->date_heure_fermeture->timezone($fuseau)->format('d/m/Y à H:i').'.' }}</strong>
                                <p>Participation : {{ number_format($participation['taux'], 1, ',', ' ') }} % ({{ $participation['votants'] }} votant(s) sur {{ $participation['inscrits'] }}).</p>
                            </div>
                            @if ($scrutin->fermetureDepassee())
                                <div class="etape-courante__actions"><form method="POST" action="{{ route('scrutins.fermer', $scrutin) }}">@csrf<button type="submit" class="btn btn-primaire"><i data-lucide="lock"></i>Fermer le scrutin maintenant</button></form></div>
                            @endif
                        @else
                            <div class="etape-courante__texte">
                                <strong>Le scrutin s’ouvrira seul le {{ $scrutin->date_heure_ouverture->timezone($fuseau)->format('d/m/Y à H:i') }}.</strong>
                                <p>Il se fermera le {{ $scrutin->date_heure_fermeture->timezone($fuseau)->format('d/m/Y à H:i') }}, une fois la phase ouverte depuis la fiche de l’édition.</p>
                            </div>
                            <div class="etape-courante__actions"><button type="button" class="btn btn-secondaire" x-data x-on:click="$dispatch('ouvrir-modale', 'planifier')"><i data-lucide="pencil"></i>Modifier les horaires</button></div>
                        @endif
                    </div>
                    @break
                @case('depouillement')
                    <div class="carte__entete"><span class="frise-phases__numero">Étape 4 sur 5</span><h2>Dépouiller le scrutin</h2></div>
                    <div class="etape-courante__corps">
                        <span class="pastille-icone"><i data-lucide="calculator"></i></span>
                        <div class="etape-courante__texte"><strong>Le vote est clos.</strong><p>Le dépouillement compte les voix et désigne les lauréates ; les résultats restent provisoires jusqu’à leur validation.</p></div>
                        <div class="etape-courante__actions"><form method="POST" action="{{ route('resultats.depouiller', $scrutin) }}">@csrf<button type="submit" class="btn btn-primaire"><i data-lucide="calculator"></i>Dépouiller le scrutin</button></form></div>
                    </div>
                    @break
                @case('resultats')
                    <div class="carte__entete"><span class="frise-phases__numero">Étape 5 sur 5</span><h2>Valider puis publier les résultats</h2></div>
                    <div class="etape-courante__corps">
                        <span class="pastille-icone"><i data-lucide="megaphone"></i></span>
                        <div class="etape-courante__texte">
                            @if ($scrutin->statut === StatutScrutin::ResultatsValides)
                                <strong>Les résultats sont validés.</strong><p>Publiez-les pour informer les électeurs et qualifier les lauréates pour la phase suivante.</p>
                            @elseif ($pv?->egalite_a_departager)
                                <strong>Une égalité doit être départagée.</strong><p>Le comité retient les lauréates parmi les ex-æquo, puis valide les résultats.</p>
                            @else
                                <strong>Vérifiez les résultats provisoires.</strong><p>À la validation, le procès-verbal reçoit son numéro et il est généré.</p>
                            @endif
                        </div>
                        <div class="etape-courante__actions"><a href="{{ route('scrutins.show', [$scrutin, 'onglet' => 'resultats']) }}#resultats" class="btn btn-primaire"><i data-lucide="trophy"></i>Voir les résultats</a></div>
                    </div>
                    @break
                @default
                    <div class="carte__entete"><h2>{{ $deroulement['annule'] ? 'Scrutin annulé' : 'Scrutin terminé' }}</h2></div>
                    <div class="etape-courante__corps">
                        <span class="pastille-icone"><i data-lucide="{{ $deroulement['annule'] ? 'circle-slash' : 'circle-check' }}"></i></span>
                        <div class="etape-courante__texte">
                            @if ($deroulement['annule'])
                                <strong>Aucune candidate n’était active à l’ouverture.</strong><p>Le scrutin a été annulé : il n’a ni vote ni résultat.</p>
                            @else
                                <strong>Les résultats sont publiés.</strong><p>Ils sont visibles de tous, avec le procès-verbal.</p>
                            @endif
                        </div>
                    </div>
            @endswitch
            @if ($rappels !== [])
                <div class="etape-courante__rappel">
                    @foreach ($rappels as $rappel)
                        <span><i data-lucide="circle-check"></i>{{ $rappel['texte'] }}</span>
                        @if ($rappel['action'] === 'rouvrir')
                            <button type="button" class="lien" data-ouvrir="modale-rouvrir">Rouvrir les listes</button>
                        @elseif ($rappel['action'] === 'planifier')
                            <button type="button" class="lien" x-data x-on:click="$dispatch('ouvrir-modale', 'planifier')">Modifier les horaires</button>
                        @endif
                    @endforeach
                </div>
            @endif
            @error('motif')<p class="erreur-champ etape-courante__rappel">{{ $message }}</p>@enderror
        </section>
    @endif

    <div class="grille-tuiles grille-tuiles--trois espace-bloc">
        <div class="tuile">
            <div class="tuile__haut"><span class="surtitre">Inscrits</span><span class="tuile__icone"><i data-lucide="users"></i></span></div>
            <div class="tuile__valeur">{{ number_format($participation['inscrits'], 0, ',', ' ') }}</div>
            <div class="tuile__detail">{{ $scrutin->listesSontModifiables() ? 'Listes en préparation' : 'Listes arrêtées' }}</div>
        </div>
        <div class="tuile">
            <div class="tuile__haut"><span class="surtitre">Candidates actives</span><span class="tuile__icone"><i data-lucide="users-round"></i></span></div>
            <div class="tuile__valeur">{{ $actives->count() }}</div>
            <div class="tuile__detail">{{ $candidates->count() - $actives->count() }} désistement(s)</div>
        </div>
        <div class="tuile">
            <div class="tuile__haut"><span class="surtitre">Participation</span><span class="tuile__icone"><i data-lucide="percent"></i></span></div>
            <div class="tuile__valeur">{{ number_format($participation['taux'], 1, ',', ' ') }} %</div>
            <div class="tuile__detail">{{ $participation['votants'] }} votant(s)</div>
        </div>
    </div>

    <section class="carte">
        <livewire:scrutins.contenu-du-scrutin :scrutin="$scrutin" />
    </section>

    <x-slot:modales>
        <livewire:scrutins.photo-candidate />
        @if ($pilote && $scrutin->accepteLesDesistements())
            <div class="voile" x-data="{ ouvert: @js($errors->hasAny(['ouverture', 'fermeture']) || ($errors->has('scrutin') && old('ouverture') !== null)) }" x-show="ouvert" x-cloak
                 x-on:ouvrir-modale.window="ouvert = $event.detail === 'planifier'" x-on:keydown.escape.window="ouvert = false" x-on:click.self="ouvert = false">
                <form method="POST" action="{{ route('scrutins.planifier', $scrutin) }}" class="modale" role="dialog" aria-modal="true" aria-labelledby="titre-planifier">
                    @csrf
                    <div class="modale__entete">
                        <span class="pastille-icone"><i data-lucide="calendar-clock"></i></span>
                        <div>
                            <h2 id="titre-planifier">Planifier le scrutin</h2>
                            <p>{{ $scrutin->libelle }} : le vote s’ouvre et se ferme automatiquement à ces heures.</p>
                        </div>
                        <button type="button" class="icone-bouton modale__fermer" aria-label="Fermer" x-on:click="ouvert = false"><i data-lucide="x"></i></button>
                    </div>
                    <div class="modale__corps formulaire formulaire--deux-colonnes">
                        <div class="formulaire__groupe">
                            <label class="libelle" for="ouverture">Ouverture <span class="requis">*</span></label>
                            <input id="ouverture" name="ouverture" type="datetime-local" class="champ" value="{{ old('ouverture', $scrutin->date_heure_ouverture?->timezone($fuseau)->format('Y-m-d\TH:i')) }}" required>
                            @error('ouverture')<p class="erreur-champ">{{ $message }}</p>@enderror
                        </div>
                        <div class="formulaire__groupe">
                            <label class="libelle" for="fermeture">Fermeture <span class="requis">*</span></label>
                            <input id="fermeture" name="fermeture" type="datetime-local" class="champ" value="{{ old('fermeture', $scrutin->date_heure_fermeture?->timezone($fuseau)->format('Y-m-d\TH:i')) }}" required>
                            @error('fermeture')<p class="erreur-champ">{{ $message }}</p>@enderror
                        </div>
                        <p class="aide formulaire__groupe--pleine">Heure du Bénin. {{ $consignePlanification }}</p>
                        @if (old('ouverture') !== null)
                            @error('scrutin')<p class="erreur-champ formulaire__groupe--pleine">{{ $message }}</p>@enderror
                        @endif
                    </div>
                    <div class="modale__pied">
                        <button type="button" class="btn btn-secondaire" x-on:click="ouvert = false">Annuler</button>
                        <button type="submit" class="btn btn-primaire"><i data-lucide="calendar-check"></i>Enregistrer les horaires</button>
                    </div>
                </form>
            </div>
        @endif
        @if ($pilote && $scrutin->listesPeuventEtreRouvertes())
            <x-modale-confirmation id="modale-rouvrir" :titre="'Rouvrir les listes de « '.$scrutin->libelle.' » ?'" :action="route('scrutins.rouvrir', $scrutin)" bouton="Rouvrir les listes" icone="lock-open">
                La décision et son motif sont tracés au journal. Le scrutin ne s’ouvrira pas tant que les listes ne seront pas de nouveau arrêtées. Les candidates désactivées le restent.
                <x-slot:champs>
                    <label class="libelle" for="motif-rouvrir">Motif de la décision du comité</label>
                    <textarea id="motif-rouvrir" name="motif" class="champ" maxlength="500" required placeholder="Ex. : agente omise de l’import RH, affectée à la TD Borgou à la date de gel"></textarea>
                </x-slot:champs>
            </x-modale-confirmation>
        @endif
    </x-slot:modales>
</x-layouts.app>
