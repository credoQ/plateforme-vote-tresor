@use('App\Enums\Acces\CodeRole')
{{-- Formulaire d'un compte : envoyé sans recharger la page ; rien ne part au serveur avant « Enregistrer ». --}}
<div class="carte">
    <div class="carte__corps">
        <form wire:submit="enregistrer" class="formulaire" novalidate>
            @foreach ([['name', 'Nom et prénoms', true], ['login', 'Identifiant (matricule pour un agent)', true], ['email', 'E-mail de connexion', $utilisateur === null]] as [$champ, $libelle, $requis])
                <div class="formulaire__groupe">
                    <label class="libelle" for="{{ $champ }}">{{ $libelle }} @if ($requis)<span class="requis">*</span>@endif</label>
                    <input id="{{ $champ }}" class="champ" wire:model="{{ $champ }}" @if ($champ === 'email') type="email" @endif>
                    @if ($champ === 'login')
                        <p class="aide">
                            @if ($utilisateur?->agent)
                                Compte lié à l’agent {{ $utilisateur->agent->matricule }} · {{ $utilisateur->agent->nomComplet() }}.
                            @else
                                Si un agent porte ce matricule, le compte lui est lié.
                            @endif
                        </p>
                    @elseif ($champ === 'email' && $utilisateur !== null)
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
                    @foreach (CodeRole::cases() as $code)
                        <label class="case case--interrupteur"><input type="checkbox" role="switch" value="{{ $code->value }}" wire:model="roles"> {{ $code->libelle() }}</label>
                    @endforeach
                </div>
                @error('roles')<p class="erreur-champ">{{ $message }}</p>@enderror
            </fieldset>

            <div class="formulaire__groupe" x-data x-show="$wire.roles.includes('{{ CodeRole::PointFocal->value }}')" @unless (in_array(CodeRole::PointFocal->value, $roles, true)) x-cloak @endunless>
                <label class="libelle" for="structure_id">Structure du point focal <span class="requis">*</span></label>
                <x-select-recherche wire:ignore>
                    <select id="structure_id" class="champ" wire:model="structureId">
                        <option value="">Aucune</option>
                        @foreach ($this->structures as $structure)
                            <option value="{{ $structure->id }}" @selected($structureId === (string) $structure->id)>{{ $structure->code }} · {{ $structure->libelle }}</option>
                        @endforeach
                    </select>
                </x-select-recherche>
                @error('structure_id')<p class="erreur-champ">{{ $message }}</p>@enderror
            </div>

            @if ($utilisateur !== null)
                <label class="case case--interrupteur"><input type="checkbox" role="switch" wire:model="actif"> Compte actif</label>
                @error('actif')<p class="erreur-champ">{{ $message }}</p>@enderror
            @endif

            <div class="formulaire__actions">
                <button type="submit" class="btn btn-primaire" wire:loading.attr="disabled" wire:target="enregistrer"><i data-lucide="save"></i>{{ $utilisateur === null ? 'Créer le compte' : 'Enregistrer le compte' }}</button>
                <a wire:navigate href="{{ route('utilisateurs.index') }}" class="btn btn-tertiaire">Retour à la liste</a>
            </div>
        </form>
    </div>
</div>
