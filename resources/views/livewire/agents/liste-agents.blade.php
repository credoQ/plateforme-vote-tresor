{{-- Liste des agents : les filtres s'appliquent pendant la saisie, sans bouton ni rechargement de la page.
     Les listes avec recherche sont ignorées par Livewire (wire:ignore) : Alpine les gère, et elles renvoient leur
     valeur au <select> natif, lié à la propriété du composant. --}}
<div>
    <div class="barre-filtres">
        <x-filtre libelle="Recherche" pour="filtre-recherche" large>
            <div class="champ-icone"><i data-lucide="search"></i><input id="filtre-recherche" type="search" class="champ" wire:model.live.debounce.400ms="recherche" placeholder="Matricule, NPI ou nom"></div>
        </x-filtre>
        <x-filtre libelle="Structure" pour="filtre-structure">
            <x-select-recherche compact wire:ignore>
                <select id="filtre-structure" class="champ" wire:model.live="structure">
                    <option value="">Toutes les structures</option>
                    @foreach ($this->structures as $choix)
                        <option value="{{ $choix->id }}" data-detail="{{ $choix->libelle }}" @selected($structure === (string) $choix->id)>{{ $choix->code }}</option>
                    @endforeach
                </select>
            </x-select-recherche>
        </x-filtre>
        <x-filtre libelle="Sexe" pour="filtre-sexe">
            <x-select-recherche compact wire:ignore>
                <select id="filtre-sexe" class="champ" wire:model.live="sexe">
                    <option value="">Tous</option>
                    @foreach (\App\Enums\Agents\Sexe::cases() as $cas)
                        <option value="{{ $cas->value }}" @selected($sexe === $cas->value)>{{ $cas->libelle() }}</option>
                    @endforeach
                </select>
            </x-select-recherche>
        </x-filtre>
        <x-filtre libelle="Situation" pour="filtre-situation">
            <x-select-recherche compact wire:ignore>
                <select id="filtre-situation" class="champ" wire:model.live="situation">
                    <option value="en-service" @selected($situation === 'en-service')>En service</option>
                    @unless ($restreint)<option value="hors-service" @selected($situation === 'hors-service')>Sans affectation en cours</option>@endunless
                    <option value="tous" @selected($situation === 'tous')>Tous</option>
                </select>
            </x-select-recherche>
        </x-filtre>
        <div class="barre-filtres__fin">
            <span class="barre-filtres__chargement" wire:loading.flex aria-live="polite"><span class="roue" aria-hidden="true"></span>Mise à jour…</span>
            @if ($this->filtresActifs)
                <a href="{{ route('agents.index') }}" class="lien" wire:navigate><i data-lucide="x"></i>Réinitialiser</a>
            @endif
            <span class="aide barre-filtres__total"><b class="chiffres">{{ number_format($this->agents->total(), 0, ',', ' ') }}</b> agent(s)</span>
        </div>
    </div>

    <div class="tableau-defilant" wire:loading.class="est-en-chargement">
        <table class="tableau tableau--fiches">
            <thead><tr><th>Matricule</th><th>Agent</th><th>Sexe</th><th>Affectation en cours</th><th>Depuis</th><th class="actions"></th></tr></thead>
            <tbody>
                @forelse ($this->agents as $agent)
                    <tr wire:key="agent-{{ $agent->id }}">
                        <td class="reference">{{ $agent->matricule }}</td>
                        <td>{{ $agent->nomComplet() }}@if ($agent->npi)<div class="aide">NPI {{ $agent->npi }}</div>@endif</td>
                        <td>{{ $agent->sexe->libelle() }}</td>
                        <td>
                            @if ($agent->affectationEnCours)
                                {{ $agent->affectationEnCours->structure->code }}<div class="aide">{{ $agent->affectationEnCours->structure->libelle }}</div>
                            @else
                                <span class="badge badge--attente">Sans affectation</span>
                            @endif
                        </td>
                        <td>{{ $agent->affectationEnCours?->date_debut->format('d/m/Y') }}</td>
                        <td class="actions"><a href="{{ route('agents.show', $agent) }}" class="btn btn-tertiaire btn--sm" wire:navigate><i data-lucide="eye"></i>Fiche</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6"><div class="etat-vide"><i data-lucide="id-card"></i><p>Aucun agent ne correspond aux filtres.</p></div></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $this->agents->onEachSide(1)->links('pagination.tresor-livewire', ['libelle' => 'agents']) }}
</div>
