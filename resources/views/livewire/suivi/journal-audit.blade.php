<div>
    <div class="barre-filtres">
        <x-filtre libelle="Opération" pour="filtre-action" large>
            <x-select-recherche compact wire:ignore>
                <select id="filtre-action" class="champ" wire:model.live="action">
                    <option value="">Toutes les opérations</option>
                    @foreach (\App\Enums\Audit\ActionAuditee::cases() as $cas)
                        <option value="{{ $cas->value }}" @selected($action === $cas->value)>{{ $cas->libelle() }}</option>
                    @endforeach
                </select>
            </x-select-recherche>
        </x-filtre>
        <x-filtre libelle="Utilisateur" pour="filtre-login">
            <input id="filtre-login" class="champ" wire:model.live.debounce.400ms="login" placeholder="Identifiant">
        </x-filtre>
        <x-filtre libelle="Du" pour="filtre-du">
            <input id="filtre-du" type="date" class="champ" wire:model.live="du">
        </x-filtre>
        <x-filtre libelle="Au" pour="filtre-au">
            <input id="filtre-au" type="date" class="champ" wire:model.live="au">
        </x-filtre>
        <x-fin-filtres :total="$this->entrees->total()" libelle="opération(s)" :actifs="$this->filtresActifs" :reinitialiser="route('journal')" />
    </div>

    <div class="tableau-defilant" wire:loading.class="est-en-chargement">
        <table class="tableau tableau--fiches">
            <thead><tr><th>Date et heure</th><th>Utilisateur</th><th>Opération</th><th>Objet</th><th>Détails</th><th>Adresse IP</th></tr></thead>
            <tbody>
                @forelse ($this->entrees as $entree)
                    <tr wire:key="entree-{{ $entree->id }}">
                        <td>{{ $entree->date_heure->timezone('Africa/Porto-Novo')->format('d/m/Y H:i:s') }}</td>
                        <td>{{ $entree->login ?? '' }}</td>
                        <td>{{ $entree->action->libelle() }}</td>
                        <td>{{ $entree->entite_type ? $entree->entite_type.' n° '.$entree->entite_id : '' }}</td>
                        <td class="details-audit">{{ $entree->details ? json_encode($entree->details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '' }}</td>
                        <td>{{ $entree->adresse_ip }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6"><div class="etat-vide"><i data-lucide="shield-check"></i><p>Aucune opération ne correspond aux filtres.</p></div></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $this->entrees->onEachSide(1)->links('pagination.tresor-livewire', ['libelle' => 'opérations']) }}
</div>
