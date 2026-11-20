<div>
    <div class="barre-filtres">
        <x-filtre libelle="Recherche" pour="filtre-recherche" large>
            <div class="champ-icone"><i data-lucide="search"></i><input id="filtre-recherche" type="search" class="champ" wire:model.live.debounce.400ms="recherche" placeholder="Nom ou identifiant"></div>
        </x-filtre>
        <x-filtre libelle="Rôle" pour="filtre-role">
            <x-select-recherche compact wire:ignore>
                <select id="filtre-role" class="champ" wire:model.live="role">
                    <option value="">Tous les rôles</option>
                    @foreach (\App\Enums\Acces\CodeRole::cases() as $code)
                        <option value="{{ $code->value }}" @selected($role === $code->value)>{{ $code->libelle() }}</option>
                    @endforeach
                </select>
            </x-select-recherche>
        </x-filtre>
        <x-fin-filtres :total="$this->utilisateurs->total()" libelle="utilisateur(s)" :actifs="$this->filtresActifs" :reinitialiser="route('utilisateurs.index')" />
    </div>

    <div class="tableau-defilant" wire:loading.class="est-en-chargement">
        <table class="tableau tableau--fiches">
            <thead><tr><th>Identifiant</th><th>Nom</th><th>Rôles</th><th>Structure</th><th>Statut</th><th class="actions">Actions</th></tr></thead>
            <tbody>
                @forelse ($this->utilisateurs as $utilisateur)
                    <tr wire:key="utilisateur-{{ $utilisateur->id }}">
                        <td>{{ $utilisateur->login }}</td>
                        <td>{{ $utilisateur->name }}</td>
                        <td>{{ $utilisateur->roles->map(fn ($r) => $r->code->libelle())->implode(', ') }}</td>
                        <td>{{ $utilisateur->structure?->code }}</td>
                        <td>
                            @if ($utilisateur->actif)
                                <span class="badge badge--succes">Actif</span>
                            @else
                                <span class="badge badge--erreur">Désactivé</span>
                            @endif
                        </td>
                        <td class="actions"><a href="{{ route('utilisateurs.edit', $utilisateur) }}" class="btn btn-tertiaire btn--sm" wire:navigate><i data-lucide="pencil"></i>Modifier</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6"><div class="etat-vide"><i data-lucide="users"></i><p>Aucun utilisateur ne correspond à la recherche.</p></div></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $this->utilisateurs->onEachSide(1)->links('pagination.tresor-livewire', ['libelle' => 'utilisateurs']) }}
</div>
