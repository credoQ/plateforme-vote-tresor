@use('App\Models\Structure')
{{-- Formulaire d'une structure, en trois étapes : envoyé sans recharger la page ; en cas d'erreur, l'étape fautive
     se rouvre (formulaire-etapes.js). Rien ne part au serveur avant « Enregistrer ». --}}
<div class="formulaire-etapes" x-data="formulaireEtapes" x-on:formulaire-erreurs.window="rouvrirEtapeEnErreur()">
    <div class="titre-page titre-page--centre">
        <h1>{{ $structure ? 'Modifier '.$structure->libelle : 'Créer une structure' }}</h1>
    </div>

    <ol class="etapes etapes-formulaire" aria-label="Étapes du formulaire" wire:ignore>
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

    <form wire:submit="enregistrer" novalidate x-on:keydown.enter="entree($event)">
        <section class="carte etape-panneau" data-etape="1" x-show="estAffichee(1)">
            <div class="carte__entete"><h2>Étape 1 sur 3 · Identification</h2></div>
            <div class="carte__corps formulaire formulaire--deux-colonnes">
                <div class="formulaire__groupe">
                    <label class="libelle" for="code">Code <span class="requis">*</span></label>
                    <input id="code" class="champ champ--majuscules" maxlength="30" wire:model="code" placeholder="Ex. : TC-OUIDAH" required>
                    @error('code')<p class="erreur-champ">{{ $message }}</p>@enderror
                </div>
                <div class="formulaire__groupe">
                    <label class="libelle" for="sigle">Sigle</label>
                    <input id="sigle" class="champ" maxlength="20" wire:model="sigle">
                    @error('sigle')<p class="erreur-champ">{{ $message }}</p>@enderror
                </div>
                <div class="formulaire__groupe formulaire__groupe--pleine">
                    <label class="libelle" for="libelle">Libellé <span class="requis">*</span></label>
                    <input id="libelle" class="champ" maxlength="160" wire:model="libelle" placeholder="Ex. : Trésorerie communale de Ouidah" required>
                    @error('libelle')<p class="erreur-champ">{{ $message }}</p>@enderror
                </div>
            </div>
        </section>

        <section class="carte etape-panneau" data-etape="2" x-show="estAffichee(2)"
                 x-data="{ typeCode: @js($codeType), get avecRattachement() { return this.$wire.niveau === @js((string) Structure::NIVEAU_SERVICE) || this.typeCode === @js($codeTypeTresorerieCommunale); } }">
            <div class="carte__entete"><h2>Étape 2 sur 3 · Classement</h2></div>
            <div class="carte__corps formulaire">
                <div class="formulaire__groupe formulaire__groupe--pleine">
                    <label class="libelle" for="type_structure_id">Type de structure <span class="requis">*</span></label>
                    <x-select-recherche placeholder="Choisir le type" wire:ignore>
                        <select id="type_structure_id" class="champ" required wire:model="typeStructureId" x-on:change="typeCode = $event.target.selectedOptions[0]?.dataset.code ?? ''">
                            <option value="">Choisir le type</option>
                            @foreach ($this->types as $type)
                                <option value="{{ $type->id }}" data-code="{{ $type->code->value }}" data-detail="{{ $type->poleElectoral->code->libelle() }}" @selected($typeStructureId === (string) $type->id)>{{ $type->code->libelle() }}</option>
                            @endforeach
                        </select>
                    </x-select-recherche>
                    @error('type_structure_id')<p class="erreur-champ">{{ $message }}</p>@enderror
                </div>
                <fieldset class="formulaire__groupe">
                    <legend class="libelle">Niveau <span class="requis">*</span></legend>
                    <div class="choix-cartes">
                        <label class="choix-carte">
                            <input type="radio" value="{{ Structure::NIVEAU_STRUCTURE_ELECTORALE }}" wire:model="niveau" required>
                            <span class="choix-carte__icone"><i data-lucide="vote"></i></span>
                            <span><strong>Structure électorale</strong><small>Elle forme sa propre zone de vote.</small></span>
                        </label>
                        <label class="choix-carte">
                            <input type="radio" value="{{ Structure::NIVEAU_SERVICE }}" wire:model="niveau">
                            <span class="choix-carte__icone"><i data-lucide="git-branch"></i></span>
                            <span><strong>Service rattaché</strong><small>Ses agents votent avec la structure de rattachement.</small></span>
                        </label>
                    </div>
                    @error('niveau')<p class="erreur-champ">{{ $message }}</p>@enderror
                </fieldset>
                <div class="formulaire__groupe" x-show="avecRattachement" @unless ($avecRattachement) x-cloak @endunless>
                    <label class="libelle" for="structure_parente_id">Structure de rattachement <span class="requis">*</span></label>
                    <x-select-recherche placeholder="Aucune" wire:ignore>
                        <select id="structure_parente_id" class="champ" wire:model="structureParenteId" x-bind:disabled="! avecRattachement">
                            <option value="">Aucune</option>
                            @foreach ($this->parentes as $parente)
                                <option value="{{ $parente->id }}" data-detail="{{ $parente->libelle }}" @selected($structureParenteId === (string) $parente->id)>{{ $parente->code }}</option>
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
                    <x-select-recherche placeholder="Aucun" wire:ignore>
                        <select id="departement_id" class="champ" wire:model="departementId">
                            <option value="">Aucun</option>
                            @foreach ($departements as $departement)
                                <option value="{{ $departement->id }}" @selected($departementId === (string) $departement->id)>{{ $departement->libelle }}</option>
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
            <a href="{{ $structure ? route('structures.show', $structure) : route('structures.index') }}" class="btn btn-tertiaire formulaire-etapes__annuler" wire:navigate>Annuler</a>
            <button type="button" class="btn btn-primaire" x-show="! estDerniere" x-cloak x-on:click="suivante()">Suivant<i data-lucide="arrow-right"></i></button>
            <button type="submit" class="btn btn-primaire formulaire-etapes__envoyer" x-bind:hidden="! estDerniere" wire:loading.attr="disabled" wire:target="enregistrer"><i data-lucide="save"></i>Enregistrer la structure</button>
        </div>
    </form>
</div>
