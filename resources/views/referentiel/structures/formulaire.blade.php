@use('App\Models\Structure')
<x-layouts.app centre :titre="$structure->exists ? 'Structure '.$structure->code : 'Créer une structure'" :ariane="['Administration', 'Structures', $structure->exists ? $structure->code : 'Créer']" actif="structures">
    <div class="formulaire-etapes" x-data="formulaireEtapes">
        <div class="titre-page titre-page--centre">
            <h1>{{ $structure->exists ? 'Modifier '.$structure->libelle : 'Créer une structure' }}</h1>
        </div>

        <ol class="etapes etapes-formulaire" aria-label="Étapes du formulaire">
            @foreach (['Identification', 'Classement', 'Localisation'] as $nomEtape)
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

        <form method="POST" action="{{ $structure->exists ? route('structures.update', $structure) : route('structures.store') }}" novalidate x-on:keydown.enter="entree($event)">
            @csrf
            @if ($structure->exists) @method('PUT') @endif

            <section class="carte etape-panneau" data-etape="1" x-show="estAffichee(1)">
                <div class="carte__entete"><h2>Étape 1 sur 3 · Identification</h2></div>
                <div class="carte__corps formulaire formulaire--deux-colonnes">
                    <div class="formulaire__groupe">
                        <label class="libelle" for="code">Code <span class="requis">*</span></label>
                        <input id="code" name="code" class="champ champ--majuscules" maxlength="30" value="{{ old('code', $structure->code) }}" placeholder="Ex. : TC-OUIDAH" required>
                        @error('code')<p class="erreur-champ">{{ $message }}</p>@enderror
                    </div>
                    <div class="formulaire__groupe">
                        <label class="libelle" for="sigle">Sigle</label>
                        <input id="sigle" name="sigle" class="champ" maxlength="20" value="{{ old('sigle', $structure->sigle) }}">
                        @error('sigle')<p class="erreur-champ">{{ $message }}</p>@enderror
                    </div>
                    <div class="formulaire__groupe formulaire__groupe--pleine">
                        <label class="libelle" for="libelle">Libellé <span class="requis">*</span></label>
                        <input id="libelle" name="libelle" class="champ" maxlength="160" value="{{ old('libelle', $structure->libelle) }}" placeholder="Ex. : Trésorerie communale de Ouidah" required>
                        @error('libelle')<p class="erreur-champ">{{ $message }}</p>@enderror
                    </div>
                </div>
            </section>

            <section class="carte etape-panneau" data-etape="2" x-show="estAffichee(2)"
                     x-data="{ niveau: @js((string) $niveau), typeCode: @js($codeType), get avecRattachement() { return this.niveau === @js((string) Structure::NIVEAU_SERVICE) || this.typeCode === 'TC'; } }">
                <div class="carte__entete"><h2>Étape 2 sur 3 · Classement</h2></div>
                <div class="carte__corps formulaire">
                    <div class="formulaire__groupe formulaire__groupe--pleine">
                        <label class="libelle" for="type_structure_id">Type de structure <span class="requis">*</span></label>
                        <x-select-recherche placeholder="Choisir le type">
                            <select id="type_structure_id" name="type_structure_id" class="champ" required x-on:change="typeCode = $event.target.selectedOptions[0]?.dataset.code ?? ''">
                                <option value="">Choisir le type</option>
                                @foreach ($types as $type)
                                    <option value="{{ $type->id }}" data-code="{{ $type->code->value }}" data-detail="{{ $type->poleElectoral->code->libelle() }}" @selected((int) old('type_structure_id', $structure->type_structure_id) === $type->id)>{{ $type->code->libelle() }}</option>
                                @endforeach
                            </select>
                        </x-select-recherche>
                        @error('type_structure_id')<p class="erreur-champ">{{ $message }}</p>@enderror
                    </div>
                    <fieldset class="formulaire__groupe">
                        <legend class="libelle">Niveau <span class="requis">*</span></legend>
                        <div class="choix-cartes">
                            <label class="choix-carte">
                                <input type="radio" name="niveau" value="{{ Structure::NIVEAU_STRUCTURE_ELECTORALE }}" x-model="niveau" @checked($niveau === Structure::NIVEAU_STRUCTURE_ELECTORALE) required>
                                <span class="choix-carte__icone"><i data-lucide="vote"></i></span>
                                <span><strong>Structure électorale</strong><small>Elle forme sa propre zone de vote.</small></span>
                            </label>
                            <label class="choix-carte">
                                <input type="radio" name="niveau" value="{{ Structure::NIVEAU_SERVICE }}" x-model="niveau" @checked($niveau === Structure::NIVEAU_SERVICE)>
                                <span class="choix-carte__icone"><i data-lucide="git-branch"></i></span>
                                <span><strong>Service rattaché</strong><small>Ses agents votent avec la structure de rattachement.</small></span>
                            </label>
                        </div>
                        @error('niveau')<p class="erreur-champ">{{ $message }}</p>@enderror
                    </fieldset>
                    <div class="formulaire__groupe" x-show="avecRattachement" @unless ($avecRattachement) x-cloak @endunless>
                        <label class="libelle" for="structure_parente_id">Structure de rattachement <span class="requis">*</span></label>
                        <x-select-recherche placeholder="Aucune">
                            <select id="structure_parente_id" name="structure_parente_id" class="champ" x-bind:disabled="! avecRattachement">
                                <option value="">Aucune</option>
                                @foreach ($parentes as $parente)
                                    <option value="{{ $parente->id }}" data-detail="{{ $parente->libelle }}" @selected((int) old('structure_parente_id', $structure->structure_parente_id) === $parente->id)>{{ $parente->code }}</option>
                                @endforeach
                            </select>
                        </x-select-recherche>
                        <p class="aide">Pour une Trésorerie communale : sa Trésorerie départementale.</p>
                        @error('structure_parente_id')<p class="erreur-champ">{{ $message }}</p>@enderror
                    </div>
                </div>
            </section>

            <section class="carte etape-panneau" data-etape="3" x-show="estAffichee(3)">
                <div class="carte__entete"><h2>Étape 3 sur 3 · Localisation</h2></div>
                <div class="carte__corps formulaire">
                    <div class="formulaire__groupe">
                        <label class="libelle" for="departement_id">Département</label>
                        <x-select-recherche placeholder="Aucun">
                            <select id="departement_id" name="departement_id" class="champ">
                                <option value="">Aucun</option>
                                @foreach ($departements as $departement)
                                    <option value="{{ $departement->id }}" @selected((int) old('departement_id', $structure->departement_id) === $departement->id)>{{ $departement->libelle }}</option>
                                @endforeach
                            </select>
                        </x-select-recherche>
                        <p class="aide">Obligatoire pour une Trésorerie départementale ou communale.</p>
                        @error('departement_id')<p class="erreur-champ">{{ $message }}</p>@enderror
                    </div>
                </div>
            </section>

            <div class="formulaire__actions formulaire-etapes__actions">
                <button type="button" class="btn btn-secondaire" x-show="etape > 1" x-cloak x-on:click="precedente()"><i data-lucide="arrow-left"></i>Précédent</button>
                <a href="{{ $structure->exists ? route('structures.show', $structure) : route('structures.index') }}" class="btn btn-tertiaire formulaire-etapes__annuler" wire:navigate>Annuler</a>
                <button type="button" class="btn btn-primaire" x-show="! estDerniere" x-cloak x-on:click="suivante()">Suivant<i data-lucide="arrow-right"></i></button>
                <button type="submit" class="btn btn-primaire formulaire-etapes__envoyer" x-bind:hidden="! estDerniere"><i data-lucide="save"></i>Enregistrer la structure</button>
            </div>
        </form>
    </div>
</x-layouts.app>
