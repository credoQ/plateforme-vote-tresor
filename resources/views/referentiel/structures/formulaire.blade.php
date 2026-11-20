@use('App\Models\Structure')
<x-layouts.app centre :titre="$structure->exists ? 'Structure '.$structure->code : 'Créer une structure'" :ariane="['Administration', 'Structures', $structure->exists ? $structure->code : 'Créer']" actif="structures">
    <div class="titre-page">
        <div>
            <h1>{{ $structure->exists ? 'Modifier '.$structure->libelle : 'Créer une structure' }}</h1>
        </div>
    </div>

    <form method="POST" action="{{ $structure->exists ? route('structures.update', $structure) : route('structures.store') }}" class="carte" novalidate>
        @csrf
        @if ($structure->exists) @method('PUT') @endif

        <section class="section-formulaire">
            <h2 class="section-formulaire__titre"><span class="organisation-phase__numero">1</span>Identification</h2>
            <div class="formulaire formulaire--deux-colonnes">
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

        <section class="section-formulaire">
            <h2 class="section-formulaire__titre"><span class="organisation-phase__numero">2</span>Classement</h2>
            <div class="formulaire formulaire--deux-colonnes">
                <div class="formulaire__groupe formulaire__groupe--pleine">
                    <label class="libelle" for="type_structure_id">Type de structure <span class="requis">*</span></label>
                    <x-select-recherche placeholder="Choisir le type">
                        <select id="type_structure_id" name="type_structure_id" class="champ" required>
                            <option value="">Choisir le type</option>
                            @foreach ($types as $type)
                                <option value="{{ $type->id }}" data-detail="{{ $type->poleElectoral->code->libelle() }}" @selected((int) old('type_structure_id', $structure->type_structure_id) === $type->id)>{{ $type->code->libelle() }}</option>
                            @endforeach
                        </select>
                    </x-select-recherche>
                    @error('type_structure_id')<p class="erreur-champ">{{ $message }}</p>@enderror
                </div>
                <fieldset class="formulaire__groupe">
                    <legend class="libelle">Niveau <span class="requis">*</span></legend>
                    <div class="choix-cartes">
                        <label class="choix-carte">
                            <input type="radio" name="niveau" value="{{ Structure::NIVEAU_STRUCTURE_ELECTORALE }}" @checked((int) old('niveau', $structure->niveau ?? Structure::NIVEAU_STRUCTURE_ELECTORALE) === Structure::NIVEAU_STRUCTURE_ELECTORALE) required>
                            <span class="choix-carte__icone"><i data-lucide="vote"></i></span>
                            <span><strong>Structure électorale</strong><small>Elle constitue sa propre circonscription.</small></span>
                        </label>
                        <label class="choix-carte">
                            <input type="radio" name="niveau" value="{{ Structure::NIVEAU_SERVICE }}" @checked((int) old('niveau', $structure->niveau) === Structure::NIVEAU_SERVICE)>
                            <span class="choix-carte__icone"><i data-lucide="git-branch"></i></span>
                            <span><strong>Service rattaché</strong><small>Ses agents votent avec la structure de rattachement.</small></span>
                        </label>
                    </div>
                    @error('niveau')<p class="erreur-champ">{{ $message }}</p>@enderror
                </fieldset>
            </div>
        </section>

        <section class="section-formulaire">
            <h2 class="section-formulaire__titre"><span class="organisation-phase__numero">3</span>Rattachement</h2>
            <div class="formulaire formulaire--deux-colonnes">
                <div class="formulaire__groupe">
                    <label class="libelle" for="structure_parente_id">Structure de rattachement</label>
                    <x-select-recherche placeholder="Aucune">
                        <select id="structure_parente_id" name="structure_parente_id" class="champ">
                            <option value="">Aucune</option>
                            @foreach ($parentes as $parente)
                                <option value="{{ $parente->id }}" data-detail="{{ $parente->libelle }}" @selected((int) old('structure_parente_id', $structure->structure_parente_id) === $parente->id)>{{ $parente->code }}</option>
                            @endforeach
                        </select>
                    </x-select-recherche>
                    <p class="aide">Obligatoire pour un service, et pour une Trésorerie communale (sa Trésorerie départementale).</p>
                    @error('structure_parente_id')<p class="erreur-champ">{{ $message }}</p>@enderror
                </div>
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

        <div class="carte__pied">
            <a href="{{ $structure->exists ? route('structures.show', $structure) : route('structures.index') }}" class="btn btn-tertiaire" wire:navigate>Annuler</a>
            <button type="submit" class="btn btn-primaire"><i data-lucide="save"></i>Enregistrer la structure</button>
        </div>
    </form>
</x-layouts.app>
