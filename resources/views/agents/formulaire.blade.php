<x-layouts.app centre :titre="! $agent->exists ? 'Saisir un agent' : 'Agent '.$agent->matricule" :ariane="['Administration', 'Agents', ! $agent->exists ? 'Saisir' : $agent->matricule]" actif="agents">
    <div class="formulaire-etapes" x-data="formulaireEtapes">
        <div class="titre-page titre-page--centre">
            <h1>{{ ! $agent->exists ? 'Saisir un agent' : 'Modifier la fiche de '.$agent->nomComplet() }}</h1>
        </div>

        <ol class="etapes etapes-formulaire" aria-label="Étapes du formulaire">
            @foreach ($agent->exists ? ['Identité', 'Informations complémentaires'] : ['Identité', 'Affectation', 'Informations complémentaires'] as $nomEtape)
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

        <form method="POST" action="{{ ! $agent->exists ? route('agents.store') : route('agents.update', $agent) }}" novalidate x-on:keydown.enter="entree($event)">
            @csrf
            @if ($agent->exists) @method('PUT') @endif

            <section class="carte etape-panneau" data-etape="1" x-show="estAffichee(1)">
                <div class="carte__entete"><h2>Étape 1 sur {{ $agent->exists ? 2 : 3 }} · Identité</h2></div>
                <div class="carte__corps formulaire formulaire--deux-colonnes">
                    @foreach ([['matricule', 'Matricule', true, 'text'], ['npi', 'NPI', false, 'text'], ['nom', 'Nom', true, 'text'], ['prenoms', 'Prénoms', true, 'text'], ['date_naissance', 'Date de naissance', false, 'date']] as [$champ, $libelle, $requis, $type])
                        <div class="formulaire__groupe">
                            <label class="libelle" for="{{ $champ }}">{{ $libelle }} @if ($requis)<span class="requis">*</span>@endif</label>
                            <input id="{{ $champ }}" name="{{ $champ }}" type="{{ $type }}" class="champ" value="{{ old($champ, $type === 'date' ? $agent->{$champ}?->format('Y-m-d') : $agent->{$champ}) }}" @required($requis)>
                            @error($champ)<p class="erreur-champ">{{ $message }}</p>@enderror
                        </div>
                    @endforeach
                    <div class="formulaire__groupe">
                        <label class="libelle" for="sexe">Sexe <span class="requis">*</span></label>
                        <x-select-recherche>
                            <select id="sexe" name="sexe" class="champ" required>
                                <option value="">Choisir</option>
                                @foreach (\App\Enums\Agents\Sexe::cases() as $cas)
                                    <option value="{{ $cas->value }}" @selected(old('sexe', $agent->sexe?->value) === $cas->value)>{{ $cas->libelle() }}</option>
                                @endforeach
                            </select>
                        </x-select-recherche>
                        @error('sexe')<p class="erreur-champ">{{ $message }}</p>@enderror
                    </div>
                </div>
            </section>

            @unless ($agent->exists)
                <section class="carte etape-panneau" data-etape="2" x-show="estAffichee(2)">
                    <div class="carte__entete"><h2>Étape 2 sur 3 · Affectation</h2></div>
                    <div class="carte__corps formulaire formulaire--deux-colonnes">
                        <div class="formulaire__groupe">
                            <label class="libelle" for="structure_id">Structure <span class="requis">*</span></label>
                            <x-select-recherche>
                                <select id="structure_id" name="structure_id" class="champ" required>
                                    <option value="">Choisir</option>
                                    @foreach ($structures as $structure)
                                        <option value="{{ $structure->id }}" @selected((int) old('structure_id') === $structure->id)>{{ $structure->code }} · {{ $structure->libelle }}</option>
                                    @endforeach
                                </select>
                            </x-select-recherche>
                            @error('structure_id')<p class="erreur-champ">{{ $message }}</p>@enderror
                        </div>
                        <div class="formulaire__groupe">
                            <label class="libelle" for="date_debut">Depuis le <span class="requis">*</span></label>
                            <input id="date_debut" name="date_debut" type="date" class="champ" value="{{ old('date_debut', now()->toDateString()) }}" required>
                            @error('date_debut')<p class="erreur-champ">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </section>
            @endunless

            <section class="carte etape-panneau" data-etape="{{ $agent->exists ? 2 : 3 }}" x-show="estAffichee({{ $agent->exists ? 2 : 3 }})">
                <div class="carte__entete"><h2>Étape {{ $agent->exists ? '2 sur 2' : '3 sur 3' }} · Informations complémentaires</h2></div>
                <div class="carte__corps formulaire formulaire--deux-colonnes">
                    @foreach ([['date_prise_service', 'Date de prise de service', 'date'], ['fonction', 'Fonction', 'text'], ['statut', 'Statut', 'text'], ['telephone', 'Téléphone', 'tel'], ['email', 'E-mail', 'email']] as [$champ, $libelle, $type])
                        <div class="formulaire__groupe">
                            <label class="libelle" for="{{ $champ }}">{{ $libelle }}</label>
                            <input id="{{ $champ }}" name="{{ $champ }}" type="{{ $type }}" class="champ" value="{{ old($champ, $type === 'date' ? $agent->{$champ}?->format('Y-m-d') : $agent->{$champ}) }}">
                            @error($champ)<p class="erreur-champ">{{ $message }}</p>@enderror
                        </div>
                    @endforeach
                </div>
            </section>

            <div class="formulaire__actions formulaire-etapes__actions">
                <button type="button" class="btn btn-secondaire" x-show="etape > 1" x-cloak x-on:click="precedente()"><i data-lucide="arrow-left"></i>Précédent</button>
                <a wire:navigate href="{{ ! $agent->exists ? route('agents.index') : route('agents.show', $agent) }}" class="btn btn-tertiaire formulaire-etapes__annuler">Annuler</a>
                <button type="button" class="btn btn-primaire" x-show="! estDerniere" x-cloak x-on:click="suivante()">Suivant<i data-lucide="arrow-right"></i></button>
                <button type="submit" class="btn btn-primaire formulaire-etapes__envoyer" x-bind:hidden="! estDerniere"><i data-lucide="save"></i>{{ ! $agent->exists ? 'Enregistrer l’agent' : 'Enregistrer la fiche' }}</button>
            </div>
        </form>
    </div>
</x-layouts.app>
