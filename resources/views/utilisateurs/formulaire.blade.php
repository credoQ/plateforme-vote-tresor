<x-layouts.app centre :titre="! $utilisateur->exists ? 'Créer un compte' : 'Compte '.$utilisateur->login" :ariane="['Administration', 'Utilisateurs', ! $utilisateur->exists ? 'Créer' : $utilisateur->login]" actif="utilisateurs">
    <div class="titre-page">
        <div>
            <h1>{{ ! $utilisateur->exists ? 'Créer un compte' : $utilisateur->name }}</h1>
        </div>
    </div>

    <div class="carte">
        <div class="carte__corps">
            <form method="POST" action="{{ ! $utilisateur->exists ? route('utilisateurs.store') : route('utilisateurs.update', $utilisateur) }}" class="formulaire" novalidate x-data="{ pointFocal: @js($pointFocal) }">
                @csrf
                @if ($utilisateur->exists) @method('PUT') @endif

                @foreach ([['name', 'Nom et prénoms', true], ['login', 'Identifiant (matricule pour un agent)', true], ['email', 'E-mail de connexion', false]] as [$champ, $libelle, $requis])
                    <div class="formulaire__groupe">
                        <label class="libelle" for="{{ $champ }}">{{ $libelle }} @if ($requis)<span class="requis">*</span>@endif</label>
                        <input id="{{ $champ }}" name="{{ $champ }}" class="champ" value="{{ old($champ, $utilisateur->{$champ}) }}" @if ($champ === 'email') type="email" @endif>
                        @if ($champ === 'login')
                            <p class="aide">
                                @if ($utilisateur->agent)
                                    Compte lié à l’agent {{ $utilisateur->agent->matricule }} · {{ $utilisateur->agent->nomComplet() }}.
                                @else
                                    Si un agent porte ce matricule, le compte lui est lié.
                                @endif
                            </p>
                        @elseif ($champ === 'email')
                            <p class="aide">Facultatif si la fiche de l’agent lié porte une adresse.</p>
                        @endif
                        @error($champ)<p class="erreur-champ">{{ $message }}</p>@enderror
                        @if ($champ === 'login')
                            @error('agent_id')<p class="erreur-champ">{{ $message }}</p>@enderror
                        @endif
                    </div>
                @endforeach

                <fieldset class="formulaire__groupe">
                    <legend class="libelle">Rôles <span class="requis">*</span></legend>
                    <div class="formulaire__cases">
                        @foreach (\App\Enums\Acces\CodeRole::cases() as $code)
                            <label class="case case--interrupteur"><input type="checkbox" role="switch" name="roles[]" value="{{ $code->value }}" @checked(in_array($code->value, old('roles', $rolesDuCompte), true)) @if ($code === \App\Enums\Acces\CodeRole::PointFocal) x-model="pointFocal" @endif> {{ $code->libelle() }}</label>
                        @endforeach
                    </div>
                    @error('roles')<p class="erreur-champ">{{ $message }}</p>@enderror
                </fieldset>

                <div class="formulaire__groupe" x-show="pointFocal" @unless ($pointFocal) x-cloak @endunless>
                    <label class="libelle" for="structure_id">Structure du point focal <span class="requis">*</span></label>
                    <x-select-recherche>
                        <select id="structure_id" name="structure_id" class="champ">
                            <option value="">Aucune</option>
                            @foreach ($structures as $structure)
                                <option value="{{ $structure->id }}" @selected((int) old('structure_id', $utilisateur->structure_id) === $structure->id)>{{ $structure->code }} · {{ $structure->libelle }}</option>
                            @endforeach
                        </select>
                    </x-select-recherche>
                    @error('structure_id')<p class="erreur-champ">{{ $message }}</p>@enderror
                </div>

                @if (! $utilisateur->exists)
                    <input type="hidden" name="actif" value="1">
                @else
                    <label class="case case--interrupteur"><input type="checkbox" role="switch" name="actif" value="1" @checked(old('actif', $utilisateur->actif))> Compte actif</label>
                    @error('actif')<p class="erreur-champ">{{ $message }}</p>@enderror
                @endif

                <div class="formulaire__actions">
                    <button type="submit" class="btn btn-primaire"><i data-lucide="save"></i>{{ ! $utilisateur->exists ? 'Créer le compte' : 'Enregistrer le compte' }}</button>
                    <a wire:navigate href="{{ route('utilisateurs.index') }}" class="btn btn-tertiaire">Retour à la liste</a>
                </div>
            </form>
        </div>
    </div>

</x-layouts.app>
