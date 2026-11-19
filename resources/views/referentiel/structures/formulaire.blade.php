<x-layouts.app :titre="$structure->exists ? 'Structure '.$structure->code : 'Créer une structure'" :ariane="['Administration', 'Structures', $structure->exists ? $structure->code : 'Créer']" actif="structures">
    <div class="titre-page">
        <div>
            <h1>{{ $structure->exists ? 'Modifier '.$structure->libelle : 'Créer une structure' }}</h1>
        </div>
    </div>

    <div class="carte">
        <div class="carte__corps">
            <form method="POST" action="{{ $structure->exists ? route('structures.update', $structure) : route('structures.store') }}" class="formulaire" novalidate>
                @csrf
                @if ($structure->exists) @method('PUT') @endif

                <fieldset class="formulaire__groupe">
                    <legend class="libelle">Identification</legend>
                    <div class="formulaire-ligne">
                        <div class="formulaire__groupe">
                            <label class="libelle" for="code">Code <span class="requis">*</span></label>
                            <input id="code" name="code" class="champ" maxlength="30" value="{{ old('code', $structure->code) }}" required>
                            @error('code')<p class="erreur-champ">{{ $message }}</p>@enderror
                        </div>
                        <div class="formulaire__groupe">
                            <label class="libelle" for="sigle">Sigle</label>
                            <input id="sigle" name="sigle" class="champ" maxlength="20" value="{{ old('sigle', $structure->sigle) }}">
                            @error('sigle')<p class="erreur-champ">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    <div class="formulaire__groupe">
                        <label class="libelle" for="libelle">Libellé <span class="requis">*</span></label>
                        <input id="libelle" name="libelle" class="champ" maxlength="160" value="{{ old('libelle', $structure->libelle) }}" required>
                        @error('libelle')<p class="erreur-champ">{{ $message }}</p>@enderror
                    </div>
                </fieldset>

                <fieldset class="formulaire__groupe">
                    <legend class="libelle">Classement</legend>
                    <div class="formulaire-ligne">
                        <div class="formulaire__groupe">
                            <label class="libelle" for="type_structure_id">Type <span class="requis">*</span></label>
                            <select id="type_structure_id" name="type_structure_id" class="champ" required>
                                <option value="">Choisir</option>
                                @foreach ($types as $type)
                                    <option value="{{ $type->id }}" @selected((int) old('type_structure_id', $structure->type_structure_id) === $type->id)>{{ $type->code->libelle() }} · {{ $type->poleElectoral->code->libelle() }}</option>
                                @endforeach
                            </select>
                            @error('type_structure_id')<p class="erreur-champ">{{ $message }}</p>@enderror
                        </div>
                        <div class="formulaire__groupe">
                            <label class="libelle" for="niveau">Niveau <span class="requis">*</span></label>
                            <select id="niveau" name="niveau" class="champ" required>
                                <option value="{{ \App\Models\Structure::NIVEAU_STRUCTURE_ELECTORALE }}" @selected((int) old('niveau', $structure->niveau) === \App\Models\Structure::NIVEAU_STRUCTURE_ELECTORALE)>Structure électorale</option>
                                <option value="{{ \App\Models\Structure::NIVEAU_SERVICE }}" @selected((int) old('niveau', $structure->niveau) === \App\Models\Structure::NIVEAU_SERVICE)>Service rattaché</option>
                            </select>
                            <p class="aide">Un service vote avec la structure à laquelle il est rattaché.</p>
                            @error('niveau')<p class="erreur-champ">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    <div class="formulaire-ligne">
                        <div class="formulaire__groupe">
                            <label class="libelle" for="structure_parente_id">Rattachée à</label>
                            <select id="structure_parente_id" name="structure_parente_id" class="champ">
                                <option value="">Aucune</option>
                                @foreach ($parentes as $parente)
                                    <option value="{{ $parente->id }}" @selected((int) old('structure_parente_id', $structure->structure_parente_id) === $parente->id)>{{ $parente->code }} · {{ $parente->libelle }}</option>
                                @endforeach
                            </select>
                            <p class="aide">Obligatoire pour un service et pour une Trésorerie communale (sa Trésorerie départementale).</p>
                            @error('structure_parente_id')<p class="erreur-champ">{{ $message }}</p>@enderror
                        </div>
                        <div class="formulaire__groupe">
                            <label class="libelle" for="departement_id">Département</label>
                            <select id="departement_id" name="departement_id" class="champ">
                                <option value="">Aucun</option>
                                @foreach ($departements as $departement)
                                    <option value="{{ $departement->id }}" @selected((int) old('departement_id', $structure->departement_id) === $departement->id)>{{ $departement->libelle }}</option>
                                @endforeach
                            </select>
                            <p class="aide">Obligatoire pour une Trésorerie départementale ou communale.</p>
                            @error('departement_id')<p class="erreur-champ">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </fieldset>

                <div class="formulaire__actions">
                    <button type="submit" class="btn btn-primaire"><i data-lucide="save"></i>Enregistrer la structure</button>
                    <a href="{{ $structure->exists ? route('structures.show', $structure) : route('structures.index') }}" class="btn btn-tertiaire">Annuler</a>
                </div>
            </form>
        </div>
    </div>
</x-layouts.app>
