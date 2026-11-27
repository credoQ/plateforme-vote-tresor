{{-- Fiche d'un agent, en étapes : envoyée sans recharger la page ; en cas d'erreur, l'étape fautive se rouvre
     (formulaire-etapes.js). Rien ne part au serveur avant « Enregistrer ». --}}
<div class="formulaire-etapes" x-data="formulaireEtapes" x-on:formulaire-erreurs.window="rouvrirEtapeEnErreur()">
    <div class="titre-page titre-page--centre">
        <h1>{{ $agent === null ? 'Saisir un agent' : 'Modifier la fiche de '.$agent->nomComplet() }}</h1>
    </div>

    <ol class="etapes etapes-formulaire" aria-label="Étapes du formulaire" wire:ignore>
        @foreach ($agent !== null ? ['Identité', 'Informations complémentaires'] : ['Identité', 'Affectation', 'Informations complémentaires'] as $nomEtape)
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
            <div class="carte__entete"><h2>Étape 1 sur {{ $agent !== null ? 2 : 3 }} · Identité</h2></div>
            <div class="carte__corps formulaire formulaire--deux-colonnes">
                @foreach ([['matricule', 'Matricule', true, 'text'], ['npi', 'NPI', false, 'text'], ['nom', 'Nom', true, 'text'], ['prenoms', 'Prénoms', true, 'text'], ['email', 'E-mail', false, 'email'], ['date_naissance', 'Date de naissance', false, 'date']] as [$champ, $libelle, $requis, $type])
                    <div class="formulaire__groupe">
                        <label class="libelle" for="{{ $champ }}">{{ $libelle }} @if ($requis)<span class="requis">*</span>@endif</label>
                        <input id="{{ $champ }}" type="{{ $type }}" class="champ" wire:model="fiche.{{ $champ }}" @required($requis)>
                        @error($champ)<p class="erreur-champ">{{ $message }}</p>@enderror
                    </div>
                @endforeach
                <div class="formulaire__groupe">
                    <label class="libelle" for="sexe">Sexe <span class="requis">*</span></label>
                    <x-select-recherche wire:ignore>
                        <select id="sexe" class="champ" required wire:model="fiche.sexe">
                            <option value="">Choisir</option>
                            @foreach ($sexes as $cas)
                                <option value="{{ $cas->value }}" @selected($fiche['sexe'] === $cas->value)>{{ $cas->libelle() }}</option>
                            @endforeach
                        </select>
                    </x-select-recherche>
                    @error('sexe')<p class="erreur-champ">{{ $message }}</p>@enderror
                </div>
            </div>
        </section>

        @if ($agent === null)
            <section class="carte etape-panneau" data-etape="2" x-show="estAffichee(2)">
                <div class="carte__entete"><h2>Étape 2 sur 3 · Affectation</h2></div>
                <div class="carte__corps formulaire formulaire--deux-colonnes">
                    <div class="formulaire__groupe">
                        <label class="libelle" for="structure_id">Structure <span class="requis">*</span></label>
                        <x-select-recherche wire:ignore>
                            <select id="structure_id" class="champ" required wire:model="structureId">
                                <option value="">Choisir</option>
                                @foreach ($this->structures as $structure)
                                    <option value="{{ $structure->id }}" @selected($structureId === (string) $structure->id)>{{ $structure->code }} · {{ $structure->libelle }}</option>
                                @endforeach
                            </select>
                        </x-select-recherche>
                        @error('structure_id')<p class="erreur-champ">{{ $message }}</p>@enderror
                    </div>
                    <div class="formulaire__groupe">
                        <label class="libelle" for="date_debut">Depuis le <span class="requis">*</span></label>
                        <input id="date_debut" type="date" class="champ" wire:model="dateDebut" required>
                        @error('date_debut')<p class="erreur-champ">{{ $message }}</p>@enderror
                    </div>
                </div>
            </section>
        @endif

        <section class="carte etape-panneau" data-etape="{{ $agent !== null ? 2 : 3 }}" x-show="estAffichee({{ $agent !== null ? 2 : 3 }})">
            <div class="carte__entete"><h2>Étape {{ $agent !== null ? '2 sur 2' : '3 sur 3' }} · Informations complémentaires</h2></div>
            <div class="carte__corps formulaire formulaire--deux-colonnes">
                @foreach ([['date_prise_service', 'Date de prise de service', 'date'], ['fonction', 'Fonction', 'text'], ['statut', 'Statut', 'text'], ['telephone', 'Téléphone', 'tel']] as [$champ, $libelle, $type])
                    <div class="formulaire__groupe">
                        <label class="libelle" for="{{ $champ }}">{{ $libelle }}</label>
                        <input id="{{ $champ }}" type="{{ $type }}" class="champ" wire:model="fiche.{{ $champ }}">
                        @error($champ)<p class="erreur-champ">{{ $message }}</p>@enderror
                    </div>
                @endforeach
            </div>
        </section>

        <div class="formulaire__actions formulaire-etapes__actions">
            <button type="button" class="btn btn-secondaire" x-show="etape > 1" x-cloak x-on:click="precedente()"><i data-lucide="arrow-left"></i>Précédent</button>
            <a wire:navigate href="{{ $agent === null ? route('agents.index') : route('agents.show', $agent) }}" class="btn btn-tertiaire formulaire-etapes__annuler">Annuler</a>
            <button type="button" class="btn btn-primaire" x-show="! estDerniere" x-cloak x-on:click="suivante()">Suivant<i data-lucide="arrow-right"></i></button>
            <button type="submit" class="btn btn-primaire formulaire-etapes__envoyer" x-bind:hidden="! estDerniere" wire:loading.attr="disabled" wire:target="enregistrer"><i data-lucide="save"></i>{{ $agent === null ? 'Enregistrer l’agent' : 'Enregistrer la fiche' }}</button>
        </div>
    </form>
</div>
