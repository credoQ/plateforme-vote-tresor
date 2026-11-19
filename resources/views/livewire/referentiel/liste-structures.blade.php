<div>
    <div class="barre-filtres">
        <x-filtre libelle="Recherche" pour="filtre-recherche" large>
            <div class="champ-icone"><i data-lucide="search"></i><input id="filtre-recherche" type="search" class="champ" wire:model.live.debounce.400ms="recherche" placeholder="Code, sigle ou libellé"></div>
        </x-filtre>
        <x-filtre libelle="Type" pour="filtre-type">
            <x-select-recherche compact wire:ignore>
                <select id="filtre-type" class="champ" wire:model.live="type">
                    <option value="">Tous les types</option>
                    @foreach ($this->types as $choix)
                        <option value="{{ $choix->id }}" @selected($type === (string) $choix->id)>{{ $choix->code->libelle() }}</option>
                    @endforeach
                </select>
            </x-select-recherche>
        </x-filtre>
        <x-filtre libelle="Département" pour="filtre-departement">
            <x-select-recherche compact wire:ignore>
                <select id="filtre-departement" class="champ" wire:model.live="departement">
                    <option value="">Tous les départements</option>
                    @foreach ($this->departements as $choix)
                        <option value="{{ $choix->id }}" @selected($departement === (string) $choix->id)>{{ $choix->libelle }}</option>
                    @endforeach
                </select>
            </x-select-recherche>
        </x-filtre>
        <x-filtre libelle="Statut" pour="filtre-statut">
            <x-select-recherche compact wire:ignore>
                <select id="filtre-statut" class="champ" wire:model.live="statut">
                    <option value="actives" @selected($statut === 'actives')>Actives</option>
                    <option value="desactivees" @selected($statut === 'desactivees')>Désactivées</option>
                    <option value="toutes" @selected($statut === 'toutes')>Toutes</option>
                </select>
            </x-select-recherche>
        </x-filtre>
        <x-fin-filtres :total="$this->structures->total()" libelle="structure(s)" :actifs="$this->filtresActifs" :reinitialiser="route('structures.index')" />
    </div>

    <div class="tableau-defilant" wire:loading.class="est-en-chargement">
        <table class="tableau tableau--fiches">
            <thead><tr><th>Code</th><th>Structure</th><th>Type</th><th>Département</th><th class="nombre">Agents en service</th><th>Statut</th><th class="actions"></th></tr></thead>
            <tbody>
                @forelse ($this->structures as $structure)
                    <tr wire:key="structure-{{ $structure->id }}">
                        <td class="reference">{{ $structure->code }}</td>
                        <td>{{ $structure->libelle }}@if ($structure->estUnService())<div class="aide">Service de {{ $structure->structureParente?->code }}</div>@endif</td>
                        <td>{{ $structure->typeStructure->code->libelle() }}</td>
                        <td>{{ $structure->departement?->libelle }}</td>
                        <td class="nombre">{{ number_format($structure->agents_en_service_count, 0, ',', ' ') }}</td>
                        <td>
                            @if ($structure->actif)
                                <span class="badge badge--succes">Active</span>
                            @else
                                <span class="badge badge--erreur">Désactivée</span>
                            @endif
                        </td>
                        <td class="actions"><a href="{{ route('structures.show', $structure) }}" class="btn btn-tertiaire btn--sm" wire:navigate><i data-lucide="eye"></i>Fiche</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7"><div class="etat-vide"><i data-lucide="building-2"></i><p>Aucune structure ne correspond aux filtres.</p></div></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $this->structures->onEachSide(1)->links('pagination.tresor-livewire', ['libelle' => 'structures']) }}
</div>
