@php
    $estNouveau = ! $utilisateur->exists;
    $rolesCoches = old('roles', $utilisateur->roles?->map(fn ($r) => $r->code->value)->all() ?? []);
@endphp
<x-layouts.app :titre="$estNouveau ? 'Créer un compte' : 'Compte '.$utilisateur->login" :ariane="['Administration', 'Utilisateurs', $estNouveau ? 'Créer' : $utilisateur->login]" actif="utilisateurs">
    <div class="titre-page">
        <div>
            <h1>{{ $estNouveau ? 'Créer un compte' : $utilisateur->name }}</h1>
        </div>
    </div>

    <div class="carte">
        <div class="carte__corps">
            <form method="POST" action="{{ $estNouveau ? route('utilisateurs.store') : route('utilisateurs.update', $utilisateur) }}" class="formulaire" novalidate>
                @csrf
                @unless ($estNouveau) @method('PUT') @endunless

                @foreach ([['name', 'Nom et prénoms', true], ['login', 'Identifiant (matricule pour un agent)', true], ['email', 'E-mail de connexion', false]] as [$champ, $libelle, $requis])
                    <div class="formulaire__groupe">
                        <label class="libelle" for="{{ $champ }}">{{ $libelle }} @if ($requis)<span class="requis">*</span>@endif</label>
                        <input id="{{ $champ }}" name="{{ $champ }}" class="champ" value="{{ old($champ, $utilisateur->{$champ}) }}" @if ($champ === 'email') type="email" @endif>
                        @error($champ)<p class="erreur-champ">{{ $message }}</p>@enderror
                    </div>
                @endforeach

                <div class="formulaire__groupe">
                    <label class="libelle" for="matricule_agent">Matricule de l’agent lié</label>
                    <input id="matricule_agent" name="matricule_agent" class="champ" value="{{ old('matricule_agent', $utilisateur->agent?->matricule) }}" placeholder="Vide pour un compte sans agent">
                    @error('matricule_agent')<p class="erreur-champ">{{ $message }}</p>@enderror
                    @error('agent_id')<p class="erreur-champ">{{ $message }}</p>@enderror
                </div>

                <fieldset class="formulaire__groupe">
                    <legend class="libelle">Rôles <span class="requis">*</span></legend>
                    <div class="formulaire__cases">
                        @foreach (\App\Enums\Acces\CodeRole::cases() as $code)
                            <label class="case"><input type="checkbox" name="roles[]" value="{{ $code->value }}" @checked(in_array($code->value, $rolesCoches, true))> {{ $code->libelle() }}</label>
                        @endforeach
                    </div>
                    @error('roles')<p class="erreur-champ">{{ $message }}</p>@enderror
                </fieldset>

                <div class="formulaire__groupe">
                    <label class="libelle" for="structure_id">Structure du point focal</label>
                    <select id="structure_id" name="structure_id" class="champ">
                        <option value="">Aucune</option>
                        @foreach ($structures as $structure)
                            <option value="{{ $structure->id }}" @selected((int) old('structure_id', $utilisateur->structure_id) === $structure->id)>{{ $structure->code }} · {{ $structure->libelle }}</option>
                        @endforeach
                    </select>
                    @error('structure_id')<p class="erreur-champ">{{ $message }}</p>@enderror
                </div>

                @if ($estNouveau)
                    <input type="hidden" name="actif" value="1">
                @else
                    <label class="case"><input type="checkbox" name="actif" value="1" @checked(old('actif', $utilisateur->actif))> Compte actif</label>
                    @error('actif')<p class="erreur-champ">{{ $message }}</p>@enderror
                @endif

                <div class="formulaire__actions">
                    <button type="submit" class="btn btn-primaire"><i data-lucide="save"></i>{{ $estNouveau ? 'Créer le compte' : 'Enregistrer le compte' }}</button>
                    <a href="{{ route('utilisateurs.index') }}" class="btn btn-tertiaire">Retour à la liste</a>
                </div>
            </form>
        </div>
    </div>

</x-layouts.app>
