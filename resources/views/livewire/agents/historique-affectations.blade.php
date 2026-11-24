{{-- Historique des affectations d'un agent : filtres et pagination sans recharger la fiche. --}}
<div>
    <div class="carte__entete"><h2>Historique des affectations</h2><span class="etiquette">{{ $this->affectations->total() }}</span></div>
    @if ($avecFiltres)
        <div class="barre-filtres">
            <x-filtre libelle="Structure" pour="filtre-recherche" large>
                <div class="champ-icone"><i data-lucide="search"></i><input id="filtre-recherche" type="search" class="champ" wire:model.live.debounce.400ms="recherche" placeholder="Code ou libellé"></div>
            </x-filtre>
            <x-filtre libelle="Période" pour="filtre-periode">
                <x-select-recherche compact wire:ignore>
                    <select id="filtre-periode" class="champ" wire:model.live="periode">
                        <option value="">Toutes</option>
                        <option value="en-cours" @selected($periode === 'en-cours')>En cours</option>
                        <option value="terminees" @selected($periode === 'terminees')>Terminées</option>
                    </select>
                </x-select-recherche>
            </x-filtre>
            <x-fin-filtres :total="$this->affectations->total()" libelle="affectation(s)" :actifs="$this->filtresActifs" :reinitialiser="route('agents.show', $agent)" />
        </div>
    @endif
    <div class="tableau-defilant" wire:loading.class="est-en-chargement">
        <table class="tableau tableau--fiches">
            <thead><tr><th>Structure</th><th>Du</th><th>Au</th><th>Durée</th><th>Statut</th></tr></thead>
            <tbody>
                @forelse ($this->affectations as $ligne)
                    <tr wire:key="affectation-{{ $ligne->id }}">
                        <td>{{ $ligne->structure->code }}<div class="aide">{{ $ligne->structure->libelle }}</div></td>
                        <td>{{ $ligne->date_debut->format('d/m/Y') }}</td>
                        <td>{{ $ligne->date_fin?->format('d/m/Y') ?? '-' }}</td>
                        <td>{{ $ligne->duree() }}</td>
                        <td>
                            @if ($ligne->date_fin === null)
                                <span class="badge badge--succes">En cours</span>
                            @else
                                <span class="badge badge--info">Terminée</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5"><div class="etat-vide"><p>{{ $this->filtresActifs ? 'Aucune affectation ne correspond aux filtres.' : 'Aucune affectation enregistrée.' }}</p></div></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $this->affectations->onEachSide(1)->links('pagination.tresor-livewire', ['libelle' => 'affectations']) }}
</div>
