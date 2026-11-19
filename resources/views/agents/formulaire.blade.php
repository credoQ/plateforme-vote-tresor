<x-layouts.app :titre="! $agent->exists ? 'Saisir un agent' : 'Agent '.$agent->matricule" :ariane="['Administration', 'Agents', ! $agent->exists ? 'Saisir' : $agent->matricule]" actif="agents">
    <div class="titre-page">
        <div>
            <h1>{{ ! $agent->exists ? 'Saisir un agent' : 'Modifier la fiche de '.$agent->nomComplet() }}</h1>
        </div>
    </div>

    <div class="carte">
        <div class="carte__corps">
            <form method="POST" action="{{ ! $agent->exists ? route('agents.store') : route('agents.update', $agent) }}" class="formulaire" novalidate>
                @csrf
                @if ($agent->exists) @method('PUT') @endif

                <fieldset class="formulaire__groupe">
                    <legend class="libelle">Identité (R11)</legend>
                    @foreach ([['matricule', 'Matricule', true, 'text'], ['npi', 'NPI', false, 'text'], ['nom', 'Nom', true, 'text'], ['prenoms', 'Prénoms', true, 'text'], ['date_naissance', 'Date de naissance', false, 'date']] as [$champ, $libelle, $requis, $type])
                        <div class="formulaire__groupe">
                            <label class="libelle" for="{{ $champ }}">{{ $libelle }} @if ($requis)<span class="requis">*</span>@endif</label>
                            <input id="{{ $champ }}" name="{{ $champ }}" type="{{ $type }}" class="champ" value="{{ old($champ, $type === 'date' ? $agent->{$champ}?->format('Y-m-d') : $agent->{$champ}) }}" @required($requis)>
                            @error($champ)<p class="erreur-champ">{{ $message }}</p>@enderror
                        </div>
                    @endforeach
                    <div class="formulaire__groupe">
                        <label class="libelle" for="sexe">Sexe <span class="requis">*</span></label>
                        <select id="sexe" name="sexe" class="champ" required>
                            <option value="">Choisir</option>
                            @foreach (\App\Enums\Agents\Sexe::cases() as $cas)
                                <option value="{{ $cas->value }}" @selected(old('sexe', $agent->sexe?->value) === $cas->value)>{{ $cas->libelle() }}</option>
                            @endforeach
                        </select>
                        @error('sexe')<p class="erreur-champ">{{ $message }}</p>@enderror
                    </div>
                </fieldset>

                @unless ($agent->exists)
                    <fieldset class="formulaire__groupe">
                        <legend class="libelle">Affectation (R12)</legend>
                        <div class="formulaire-ligne">
                            <div class="formulaire__groupe">
                                <label class="libelle" for="structure_id">Structure <span class="requis">*</span></label>
                                <select id="structure_id" name="structure_id" class="champ" required>
                                    <option value="">Choisir</option>
                                    @foreach ($structures as $structure)
                                        <option value="{{ $structure->id }}" @selected((int) old('structure_id') === $structure->id)>{{ $structure->code }} · {{ $structure->libelle }}</option>
                                    @endforeach
                                </select>
                                @error('structure_id')<p class="erreur-champ">{{ $message }}</p>@enderror
                            </div>
                            <div class="formulaire__groupe">
                                <label class="libelle" for="date_debut">Depuis le <span class="requis">*</span></label>
                                <input id="date_debut" name="date_debut" type="date" class="champ" value="{{ old('date_debut', now()->toDateString()) }}" required>
                                @error('date_debut')<p class="erreur-champ">{{ $message }}</p>@enderror
                            </div>
                        </div>
                    </fieldset>
                @endunless

                <fieldset class="formulaire__groupe">
                    <legend class="libelle">Informations complémentaires</legend>
                    @foreach ([['date_prise_service', 'Date de prise de service', 'date'], ['fonction', 'Fonction', 'text'], ['statut', 'Statut', 'text'], ['telephone', 'Téléphone', 'tel'], ['email', 'E-mail', 'email']] as [$champ, $libelle, $type])
                        <div class="formulaire__groupe">
                            <label class="libelle" for="{{ $champ }}">{{ $libelle }}</label>
                            <input id="{{ $champ }}" name="{{ $champ }}" type="{{ $type }}" class="champ" value="{{ old($champ, $type === 'date' ? $agent->{$champ}?->format('Y-m-d') : $agent->{$champ}) }}">
                            @error($champ)<p class="erreur-champ">{{ $message }}</p>@enderror
                        </div>
                    @endforeach
                </fieldset>

                <div class="formulaire__actions">
                    <button type="submit" class="btn btn-primaire"><i data-lucide="save"></i>{{ ! $agent->exists ? 'Enregistrer l’agent' : 'Enregistrer la fiche' }}</button>
                    <a href="{{ ! $agent->exists ? route('agents.index') : route('agents.show', $agent) }}" class="btn btn-tertiaire">Annuler</a>
                </div>
            </form>
        </div>
    </div>
</x-layouts.app>
