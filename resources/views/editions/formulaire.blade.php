<x-layouts.app centre :titre="! $edition->exists ? 'Créer une édition' : 'Édition '.$edition->annee" :ariane="['Pilotage', 'Éditions', ! $edition->exists ? 'Créer' : (string) $edition->annee]" actif="editions">
    <div class="formulaire-etapes" x-data="formulaireEtapes">
        <div class="titre-page titre-page--centre">
            <h1>{{ ! $edition->exists ? 'Créer une édition' : 'Paramétrer l’édition '.$edition->annee }}</h1>
        </div>

        <ol class="etapes etapes-formulaire" aria-label="Étapes du formulaire">
            @foreach (['Édition', 'Paramètres du vote', 'Phases et collèges', 'Récapitulatif'] as $nomEtape)
                <li class="etape" x-bind:class="etat({{ $loop->iteration }})" x-bind:aria-current="etape === {{ $loop->iteration }} ? 'step' : null">
                    <button type="button" class="etape__bouton" x-on:click="allerA({{ $loop->iteration }})" x-bind:disabled="{{ $loop->iteration }} > etape">
                        <span class="etape__puce">
                            <span x-show="etat({{ $loop->iteration }}) !== 'est-faite'">{{ $loop->iteration }}</span>
                            <svg x-show="etat({{ $loop->iteration }}) === 'est-faite'" x-cloak viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
                        </span>
                        <span class="etape__texte">{{ $nomEtape }}</span>
                    </button>
                </li>
                @unless ($loop->last)
                    <li class="etape__trait" aria-hidden="true"></li>
                @endunless
            @endforeach
        </ol>

        <form method="POST" action="{{ ! $edition->exists ? route('editions.store') : route('editions.update', $edition) }}" novalidate
              x-on:keydown.enter="entree($event)" x-on:input="version++" x-on:change="version++">
            @csrf
            @if ($edition->exists) @method('PUT') @endif

            <section class="carte etape-panneau" data-etape="1" x-show="estAffichee(1)">
                <div class="carte__entete"><h2>Étape 1 sur 4 · Édition</h2></div>
                <div class="carte__corps formulaire formulaire--deux-colonnes">
                    @foreach ([['annee', 'Année', 'number'], ['libelle', 'Libellé', 'text'], ['date_debut', 'Début de l’édition', 'date'], ['date_fin', 'Fin de l’édition', 'date'], ['date_gel', 'Gel des listes électorales', 'date']] as [$champ, $libelle, $type])
                        <div class="formulaire__groupe">
                            <label class="libelle" for="{{ $champ }}">{{ $libelle }} <span class="requis">*</span></label>
                            <input id="{{ $champ }}" name="{{ $champ }}" type="{{ $type }}" class="champ" value="{{ old($champ, $type === 'date' ? $edition->{$champ}?->format('Y-m-d') : $edition->{$champ}) }}" required>
                            @if ($champ === 'date_gel')
                                <p class="aide">Les agents en service à cette date forment les listes électorales et les candidates d’office. Une mutation après cette date n’y change rien.</p>
                            @endif
                            @error($champ)<p class="erreur-champ">{{ $message }}</p>@enderror
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="carte etape-panneau" data-etape="2" x-show="estAffichee(2)">
                <div class="carte__entete"><h2>Étape 2 sur 4 · Paramètres du vote</h2></div>
                <div class="carte__corps formulaire">
                    <fieldset class="formulaire__groupe">
                        <legend class="libelle">Bulletin</legend>
                        <label class="case"><input type="checkbox" name="vote_blanc_autorise" value="1" @checked(old('vote_blanc_autorise', $edition->vote_blanc_autorise))> Autoriser le vote blanc</label>
                        <p class="aide">L’électeur peut valider un bulletin sans choisir de candidate. Il compte comme votant, mais pas comme suffrage exprimé.</p>
                        <label class="case"><input type="checkbox" name="vote_pour_soi_autorise" value="1" @checked(old('vote_pour_soi_autorise', $edition->exists ? $edition->vote_pour_soi_autorise : true))> Autoriser une candidate à voter pour elle-même</label>
                    </fieldset>

                    <div class="formulaire__groupe">
                        <label class="libelle" for="delai_reeligibilite">Délai avant qu’une femme modèle puisse être réélue (années)</label>
                        <input id="delai_reeligibilite" name="delai_reeligibilite" type="number" min="0" max="20" class="champ champ--court" value="{{ old('delai_reeligibilite', $edition->delai_reeligibilite) }}">
                        <p class="aide">Avec 3 ans, une femme modèle élue en 2024 peut de nouveau être candidate en 2028.</p>
                        @error('delai_reeligibilite')<p class="erreur-champ">{{ $message }}</p>@enderror
                    </div>

                    <div class="formulaire__groupe">
                        <label class="libelle" for="quorum">Quorum de participation (%)</label>
                        <input id="quorum" name="quorum" type="number" min="1" max="100" step="0.01" class="champ champ--moyen" value="{{ old('quorum', $edition->quorum) }}" placeholder="Aucun quorum">
                        <p class="aide">Participation minimale de chaque scrutin. Si elle n’est pas atteinte à la clôture, le comité peut prolonger le scrutin. Laisser vide pour n’en fixer aucun.</p>
                        @error('quorum')<p class="erreur-champ">{{ $message }}</p>@enderror
                    </div>
                </div>
            </section>

            <section class="carte etape-panneau" data-etape="3" x-show="estAffichee(3)">
                <div class="carte__entete"><h2>Étape 3 sur 4 · Phases et collèges</h2></div>
                <div class="carte__corps">
                    @if (! $organisationModifiable)
                        <div class="alerte alerte--info"><i data-lucide="lock"></i><div>Les scrutins sont générés : les phases et leurs collèges ne se modifient plus.</div></div>
                    @else
                        @error('phases')<div class="alerte alerte--erreur espace-bloc" role="alert"><i data-lucide="circle-alert"></i><div>{{ $message }}</div></div>@enderror
                        @if ($errors->has('phases.*'))
                            <div class="alerte alerte--erreur espace-bloc" role="alert"><i data-lucide="circle-alert"></i><div>{{ $errors->first('phases.*') }}</div></div>
                        @endif

                        <div class="organisation-phases" x-data="{ phases: {{ $phasesAffichees }} }">
                            @foreach ($organisation as $numero => $phase)
                                <fieldset class="organisation-phase" @if ($numero > 1) x-show="phases >= {{ $numero }}" @if ($numero > $phasesAffichees) x-cloak @endif @endif>
                                    <legend class="organisation-phase__titre"><span class="organisation-phase__numero">{{ $numero }}</span>Phase {{ $numero }}</legend>
                                    @if ($numero > 1)
                                        <input type="hidden" name="phases[{{ $numero }}][active]" value="{{ $numero <= $phasesAffichees ? '1' : '' }}" x-bind:value="phases >= {{ $numero }} ? '1' : ''">
                                    @endif
                                    <div class="formulaire__groupe">
                                        <label class="libelle" for="phase-{{ $numero }}-libelle">Libellé</label>
                                        <input id="phase-{{ $numero }}-libelle" name="phases[{{ $numero }}][libelle]" class="champ" maxlength="80" value="{{ old("phases.$numero.libelle", $phase['libelle']) }}">
                                    </div>
                                    <div class="tableau-defilant">
                                        <table class="tableau">
                                            <thead><tr><th>Collège</th><th>Scrutins</th><th>Candidates</th><th class="nombre">Élues par scrutin</th></tr></thead>
                                            <tbody>
                                                @foreach ($colleges as $cle => $libelleCollege)
                                                    <tr>
                                                        <td>{{ $libelleCollege }}</td>
                                                        <td>
                                                            <x-select-recherche compact>
                                                                <select name="phases[{{ $numero }}][colleges][{{ $cle }}][decoupage]" class="champ" aria-label="Scrutins du collège {{ $libelleCollege }}, phase {{ $numero }}">
                                                                    <option value="AUCUN">Aucun scrutin</option>
                                                                    @foreach ($decoupages as $valeur => $libelle)
                                                                        @if ($cle !== 'DGTCP' || $valeur === 'ENSEMBLE')
                                                                            <option value="{{ $valeur }}" @selected(old("phases.$numero.colleges.$cle.decoupage", $phase['colleges'][$cle]['decoupage']) === $valeur)>{{ $libelle }}</option>
                                                                        @endif
                                                                    @endforeach
                                                                </select>
                                                            </x-select-recherche>
                                                        </td>
                                                        <td>
                                                            <x-select-recherche compact>
                                                                <select name="phases[{{ $numero }}][colleges][{{ $cle }}][origine]" class="champ" aria-label="Candidates du collège {{ $libelleCollege }}, phase {{ $numero }}">
                                                                    @foreach ($origines as $valeur => $libelle)
                                                                        <option value="{{ $valeur }}" @selected(old("phases.$numero.colleges.$cle.origine", $phase['colleges'][$cle]['origine']) === $valeur)>{{ $libelle }}</option>
                                                                    @endforeach
                                                                </select>
                                                            </x-select-recherche>
                                                        </td>
                                                        <td class="nombre">
                                                            <input type="number" min="1" max="20" name="phases[{{ $numero }}][colleges][{{ $cle }}][nb_elues]" class="champ champ--court" value="{{ old("phases.$numero.colleges.$cle.nb_elues", $phase['colleges'][$cle]['nb_elues']) }}" aria-label="Élues par scrutin, collège {{ $libelleCollege }}, phase {{ $numero }}">
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                    @if ($numero > 1)
                                        <button type="button" class="btn btn-tertiaire organisation-phase__retirer" x-show="phases === {{ $numero }}" x-on:click="phases--"><i data-lucide="x"></i>Retirer la phase {{ $numero }}</button>
                                    @endif
                                </fieldset>
                            @endforeach
                            <div x-show="phases < {{ count($organisation) }}" @if ($phasesAffichees >= count($organisation)) x-cloak @endif>
                                <button type="button" class="btn btn-secondaire" x-on:click="phases++"><i data-lucide="plus"></i>Ajouter une phase</button>
                            </div>
                        </div>
                    @endif
                </div>
            </section>

            <section class="carte etape-panneau" data-etape="4" x-show="estAffichee(4)">
                <div class="carte__entete"><h2>Étape 4 sur 4 · Récapitulatif</h2></div>
                <div class="carte__corps recapitulatif">
                    <dl class="recapitulatif__liste">
                        <div><dt>Année</dt><dd x-text="valeur('annee')"></dd></div>
                        <div><dt>Libellé</dt><dd x-text="valeur('libelle')"></dd></div>
                        <div><dt>Calendrier</dt><dd>du <span x-text="valeur('date_debut')"></span> au <span x-text="valeur('date_fin')"></span></dd></div>
                        <div><dt>Gel des listes électorales</dt><dd x-text="valeur('date_gel')"></dd></div>
                        <div><dt>Vote blanc autorisé</dt><dd x-text="valeur('vote_blanc_autorise')"></dd></div>
                        <div><dt>Vote pour soi-même autorisé</dt><dd x-text="valeur('vote_pour_soi_autorise')"></dd></div>
                        <div><dt>Délai de rééligibilité</dt><dd><span x-text="valeur('delai_reeligibilite')"></span> an(s)</dd></div>
                        <div><dt>Quorum de participation</dt><dd x-text="valeur('quorum')"></dd></div>
                    </dl>

                    @if ($organisationModifiable)
                        <h3 class="recapitulatif__titre">Phases et collèges</h3>
                        <div class="recapitulatif__phases">
                            @foreach ($organisation as $numero => $phase)
                                <div class="recapitulatif__phase" @if ($numero > 1) x-show="brut('phases[{{ $numero }}][active]') === '1'" @endif>
                                    <p><span class="organisation-phase__numero">{{ $numero }}</span><strong x-text="valeur('phases[{{ $numero }}][libelle]')"></strong></p>
                                    <ul>
                                        @foreach ($colleges as $cle => $libelleCollege)
                                            <li x-show="brut('phases[{{ $numero }}][colleges][{{ $cle }}][decoupage]') !== 'AUCUN'">
                                                {{ $libelleCollege }} : <span x-text="valeur('phases[{{ $numero }}][colleges][{{ $cle }}][decoupage]')"></span>,
                                                <span x-text="valeur('phases[{{ $numero }}][colleges][{{ $cle }}][origine]')"></span>,
                                                <span x-text="valeur('phases[{{ $numero }}][colleges][{{ $cle }}][nb_elues]')"></span> élue(s) par scrutin
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </section>

            <div class="formulaire__actions formulaire-etapes__actions">
                <button type="button" class="btn btn-secondaire" x-show="etape > 1" x-cloak x-on:click="precedente()"><i data-lucide="arrow-left"></i>Précédent</button>
                <a href="{{ ! $edition->exists ? route('editions.index') : route('editions.show', $edition) }}" class="btn btn-tertiaire formulaire-etapes__annuler">Annuler</a>
                <button type="button" class="btn btn-primaire" x-show="! estDerniere" x-cloak x-on:click="suivante()">Suivant<i data-lucide="arrow-right"></i></button>
                <button type="submit" class="btn btn-primaire formulaire-etapes__envoyer" x-bind:hidden="! estDerniere"><i data-lucide="save"></i>{{ ! $edition->exists ? 'Créer l’édition' : 'Enregistrer l’édition' }}</button>
            </div>
        </form>
    </div>
</x-layouts.app>
