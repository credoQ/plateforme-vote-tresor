<div>
    <div class="barre-filtres">
        <x-filtre libelle="Recherche" pour="filtre-recherche" large>
            <div class="champ-icone"><i data-lucide="search"></i><input id="filtre-recherche" type="search" class="champ" wire:model.live.debounce.400ms="recherche" placeholder="Année ou libellé"></div>
        </x-filtre>
        <x-filtre libelle="Statut" pour="filtre-statut">
            <x-select-recherche compact wire:ignore>
                <select id="filtre-statut" class="champ" wire:model.live="statut">
                    <option value="">Tous les statuts</option>
                    @foreach (\App\Enums\Editions\StatutEdition::cases() as $cas)
                        <option value="{{ $cas->value }}" @selected($statut === $cas->value)>{{ $cas->libelle() }}</option>
                    @endforeach
                </select>
            </x-select-recherche>
        </x-filtre>
        <x-fin-filtres :total="$this->editions->total()" libelle="édition(s)" :actifs="$this->filtresActifs" :reinitialiser="route('editions.index')" />
    </div>
    <div class="tableau-defilant" wire:loading.class="est-en-chargement">
        <table class="tableau tableau--fiches">
            <thead><tr><th>Année</th><th>Libellé</th><th>Calendrier</th><th>Gel des listes</th><th class="nombre">Scrutins</th><th>Statut</th><th class="actions"></th></tr></thead>
            <tbody>
                @forelse ($this->editions as $edition)
                    <tr wire:key="edition-{{ $edition->id }}">
                        <td class="reference">{{ $edition->annee }}</td>
                        <td>{{ $edition->libelle }}</td>
                        <td>du {{ $edition->date_debut->format('d/m/Y') }} au {{ $edition->date_fin->format('d/m/Y') }}</td>
                        <td>{{ $edition->date_gel->format('d/m/Y') }}</td>
                        <td class="nombre">{{ $edition->scrutins_count }}</td>
                        <td><span class="badge {{ $edition->statut->badge() }}">{{ $edition->statut->libelle() }}</span></td>
                        <td class="actions"><a href="{{ route('editions.show', $edition) }}" class="btn btn-tertiaire btn--sm" wire:navigate><i data-lucide="eye"></i>Ouvrir</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7"><div class="etat-vide"><i data-lucide="calendar-plus"></i><p>Aucune édition.</p></div></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $this->editions->onEachSide(1)->links('pagination.tresor-livewire', ['libelle' => 'éditions']) }}
</div>
