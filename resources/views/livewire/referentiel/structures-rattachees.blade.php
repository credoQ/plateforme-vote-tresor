{{-- Structures rattachées sur la fiche d'une structure : recherche, statut et pagination sans recharger la fiche. --}}
<div>
    <div class="barre-filtres">
        <x-filtre libelle="Recherche" pour="filtre-recherche" large>
            <div class="champ-icone"><i data-lucide="search"></i><input id="filtre-recherche" type="search" class="champ" wire:model.live.debounce.400ms="recherche" placeholder="Code, sigle ou libellé"></div>
        </x-filtre>
        <x-filtre libelle="Statut" pour="filtre-statut">
            <x-select-recherche compact wire:ignore>
                <select id="filtre-statut" class="champ" wire:model.live="statut">
                    <option value="toutes" @selected($statut === 'toutes')>Toutes</option>
                    <option value="actives" @selected($statut === 'actives')>Actives</option>
                    <option value="desactivees" @selected($statut === 'desactivees')>Désactivées</option>
                </select>
            </x-select-recherche>
        </x-filtre>
        <x-fin-filtres :total="$this->structuresFilles->total()" libelle="structure(s)" :actifs="$this->filtresActifs" :reinitialiser="route('structures.show', $structure)" />
    </div>
    <div class="tableau-defilant" wire:loading.class="est-en-chargement">
        <table class="tableau tableau--fiches">
            <thead><tr><th>Code</th><th>Structure</th><th class="nombre">Agents</th><th>Statut</th></tr></thead>
            <tbody>
                @forelse ($this->structuresFilles as $fille)
                    <tr wire:key="structure-{{ $fille->id }}">
                        <td class="reference"><a wire:navigate href="{{ route('structures.show', $fille) }}">{{ $fille->code }}</a></td>
                        <td>{{ $fille->libelle }}<div class="aide">{{ $fille->estUnService() ? 'Service' : $fille->typeStructure->code->libelle() }}</div></td>
                        <td class="nombre">{{ number_format($fille->agents_en_service_count, 0, ',', ' ') }}</td>
                        <td>
                            @if ($fille->actif)
                                <span class="badge badge--succes">Active</span>
                            @else
                                <span class="badge badge--erreur">Désactivée</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4"><div class="etat-vide"><p>{{ $this->filtresActifs ? 'Aucune structure rattachée ne correspond aux filtres.' : 'Aucune structure rattachée.' }}</p></div></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $this->structuresFilles->onEachSide(1)->links('pagination.tresor-livewire', ['libelle' => 'structures rattachées']) }}
</div>
