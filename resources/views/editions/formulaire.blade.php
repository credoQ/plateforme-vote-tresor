<x-layouts.app :titre="! $edition->exists ? 'Créer une édition' : 'Édition '.$edition->annee" :ariane="['Pilotage', 'Éditions', ! $edition->exists ? 'Créer' : (string) $edition->annee]" actif="editions">
    <div class="titre-page">
        <div>
            <h1>{{ ! $edition->exists ? 'Créer une édition' : 'Paramétrer l’édition '.$edition->annee }}</h1>
        </div>
    </div>

    <form method="POST" action="{{ ! $edition->exists ? route('editions.store') : route('editions.update', $edition) }}" novalidate>
        @csrf
        @if ($edition->exists) @method('PUT') @endif

        <section class="carte espace-bloc">
            <div class="carte__entete"><h2>Paramètres de l’édition</h2></div>
            <div class="carte__corps formulaire">
                @foreach ([['annee', 'Année', 'number'], ['libelle', 'Libellé', 'text'], ['date_debut', 'Début de l’édition', 'date'], ['date_fin', 'Fin de l’édition', 'date'], ['date_gel', 'Gel des listes électorales', 'date']] as [$champ, $libelle, $type])
                    <div class="formulaire__groupe">
                        <label class="libelle" for="{{ $champ }}">{{ $libelle }} <span class="requis">*</span></label>
                        <input id="{{ $champ }}" name="{{ $champ }}" type="{{ $type }}" class="champ" value="{{ old($champ, $type === 'date' ? $edition->{$champ}?->format('Y-m-d') : $edition->{$champ}) }}" required>
                        @error($champ)<p class="erreur-champ">{{ $message }}</p>@enderror
                    </div>
                @endforeach

                <fieldset class="formulaire__groupe">
                    <legend class="libelle">Paramètres du vote</legend>
                    <label class="case"><input type="checkbox" name="vote_blanc_autorise" value="1" @checked(old('vote_blanc_autorise', $edition->vote_blanc_autorise))> Autoriser le vote blanc</label>
                    <label class="case"><input type="checkbox" name="vote_pour_soi_autorise" value="1" @checked(old('vote_pour_soi_autorise', $edition->exists ? $edition->vote_pour_soi_autorise : true))> Autoriser une candidate à voter pour elle-même</label>
                </fieldset>

                <div class="formulaire__groupe">
                    <label class="libelle" for="quorum">Quorum de participation (%)</label>
                    <input id="quorum" name="quorum" type="number" min="1" max="100" step="0.01" class="champ" value="{{ old('quorum', $edition->quorum) }}" placeholder="Aucun quorum">
                    @error('quorum')<p class="erreur-champ">{{ $message }}</p>@enderror
                </div>
            </div>
        </section>

        <section class="carte espace-bloc">
            <div class="carte__entete"><h2>Phases et collèges</h2></div>
            <div class="carte__corps">
                @if (! $organisationModifiable)
                    <div class="alerte alerte--info"><i data-lucide="lock"></i><div>Les scrutins sont générés : les phases et leurs collèges ne se modifient plus.</div></div>
                @else
                    @error('phases')<div class="alerte alerte--erreur espace-bloc" role="alert"><i data-lucide="circle-alert"></i><div>{{ $message }}</div></div>@enderror
                    @if ($errors->has('phases.*'))
                        <div class="alerte alerte--erreur espace-bloc" role="alert"><i data-lucide="circle-alert"></i><div>{{ $errors->first('phases.*') }}</div></div>
                    @endif

                    <div class="organisation-phases">
                        @foreach ($organisation as $numero => $phase)
                            <fieldset class="organisation-phase">
                                <legend class="libelle">Phase {{ $numero }}</legend>
                                @if ($numero > 1)
                                    <label class="case"><input type="checkbox" name="phases[{{ $numero }}][active]" value="1" @checked(old('annee') !== null ? old("phases.$numero.active") : $phase['active'])> Organiser une phase {{ $numero }}</label>
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
                                                        <select name="phases[{{ $numero }}][colleges][{{ $cle }}][decoupage]" class="champ" aria-label="Scrutins du collège {{ $libelleCollege }}, phase {{ $numero }}">
                                                            <option value="AUCUN">Aucun scrutin</option>
                                                            @foreach ($decoupages as $valeur => $libelle)
                                                                @if ($cle !== 'DGTCP' || $valeur === 'ENSEMBLE')
                                                                    <option value="{{ $valeur }}" @selected(old("phases.$numero.colleges.$cle.decoupage", $phase['colleges'][$cle]['decoupage']) === $valeur)>{{ $libelle }}</option>
                                                                @endif
                                                            @endforeach
                                                        </select>
                                                    </td>
                                                    <td>
                                                        <select name="phases[{{ $numero }}][colleges][{{ $cle }}][origine]" class="champ" aria-label="Candidates du collège {{ $libelleCollege }}, phase {{ $numero }}">
                                                            @foreach ($origines as $valeur => $libelle)
                                                                <option value="{{ $valeur }}" @selected(old("phases.$numero.colleges.$cle.origine", $phase['colleges'][$cle]['origine']) === $valeur)>{{ $libelle }}</option>
                                                            @endforeach
                                                        </select>
                                                    </td>
                                                    <td class="nombre">
                                                        <input type="number" min="1" max="20" name="phases[{{ $numero }}][colleges][{{ $cle }}][nb_elues]" class="champ champ--court" value="{{ old("phases.$numero.colleges.$cle.nb_elues", $phase['colleges'][$cle]['nb_elues']) }}" aria-label="Élues par scrutin, collège {{ $libelleCollege }}, phase {{ $numero }}">
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </fieldset>
                        @endforeach
                    </div>
                @endif
            </div>
        </section>

        <div class="formulaire__actions">
            <button type="submit" class="btn btn-primaire"><i data-lucide="save"></i>{{ ! $edition->exists ? 'Créer l’édition' : 'Enregistrer l’édition' }}</button>
            <a href="{{ ! $edition->exists ? route('editions.index') : route('editions.show', $edition) }}" class="btn btn-tertiaire">Annuler</a>
        </div>
    </form>
</x-layouts.app>
