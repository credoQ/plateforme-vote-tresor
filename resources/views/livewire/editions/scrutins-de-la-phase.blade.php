@use('App\Enums\Scrutins\StatutScrutin')
{{-- Onglets de la phase sur la fiche d'une édition : seule cette zone est mise à jour au changement d'onglet, de
     filtre ou de page. Les liens gardent leur adresse (ouverture dans un nouvel onglet, page sans JavaScript). --}}
<div>
    <nav class="onglets" aria-label="Rubriques de la phase">
        @foreach (['scrutins' => 'Scrutins', 'colleges' => 'Périmètres', 'resultats' => 'Résultats à valider'] as $cle => $libelleOnglet)
            <a href="{{ route('editions.show', [$phase->edition, 'phase' => $phase->numero, 'onglet' => $cle]) }}" wire:click.prevent="choisirOnglet('{{ $cle }}')"
               @class(['onglet', 'est-actif' => $onglet === $cle]) @if ($onglet === $cle) aria-current="page" @endif>
                {{ $libelleOnglet }} <span @class(['compteur', 'compteur--attente' => $cle === 'resultats' && $this->compteurs['resultats'] > 0])>{{ $this->compteurs[$cle] }}</span>
            </a>
        @endforeach
    </nav>

    @if ($onglet === 'colleges')
        <div class="carte__corps">
            <ul class="colleges-phase" aria-label="Périmètres de la phase">
                @foreach ($collegesDeLaPhase as $collegeDeLaPhase)
                    <li><i data-lucide="users-round"></i>{{ $collegeDeLaPhase->resume() }}</li>
                @endforeach
            </ul>
        </div>
    @elseif ($this->compteurs['scrutins'] === 0)
        <div class="etat-vide">
            <i data-lucide="clipboard-list"></i>
            <p>Les scrutins de cette phase ne sont pas encore générés.</p>
        </div>
    @else
        <div class="barre-filtres">
            <x-filtre libelle="Recherche" pour="filtre-recherche" large>
                <div class="champ-icone"><i data-lucide="search"></i><input id="filtre-recherche" type="search" class="champ" wire:model.live.debounce.400ms="recherche" placeholder="Scrutin ou code"></div>
            </x-filtre>
            <x-filtre libelle="Périmètre" pour="filtre-college">
                <x-select-recherche compact wire:ignore>
                    <select id="filtre-college" class="champ" wire:model.live="college">
                        <option value="">Tous les périmètres</option>
                        @foreach ($colleges as $valeur => $libelle)
                            <option value="{{ $valeur }}" @selected($college === $valeur)>{{ $libelle }}</option>
                        @endforeach
                    </select>
                </x-select-recherche>
            </x-filtre>
            @if ($onglet === 'scrutins')
                <x-filtre libelle="Statut" pour="filtre-statut">
                    <x-select-recherche compact wire:ignore wire:key="filtre-statut">
                        <select id="filtre-statut" class="champ" wire:model.live="statut">
                            <option value="">Tous les statuts</option>
                            @foreach (StatutScrutin::cases() as $cas)
                                <option value="{{ $cas->value }}" @selected($statut === $cas->value)>{{ $cas->libelle() }}</option>
                            @endforeach
                        </select>
                    </x-select-recherche>
                </x-filtre>
            @endif
            <x-fin-filtres :total="$this->scrutins->total()" libelle="scrutin(s)" :actifs="$this->filtresActifs" :reinitialiser="route('editions.show', [$phase->edition, 'phase' => $phase->numero, 'onglet' => $onglet])" />
        </div>
        <div class="tableau-defilant" wire:loading.class="est-en-chargement">
            <table class="tableau tableau--fiches">
                <thead><tr><th>Scrutin</th><th class="nombre">Inscrits</th><th class="nombre">Candidates</th><th>Ouverture</th><th class="nombre">Participation</th><th>Statut</th><th class="actions"></th></tr></thead>
                <tbody>
                    @forelse ($this->scrutins as $ligne)
                        <tr wire:key="scrutin-{{ $ligne['scrutin']->id }}">
                            <td>{{ $ligne['scrutin']->libelle }}<div class="aide">{{ $ligne['scrutin']->code }}</div></td>
                            <td class="nombre">{{ number_format($ligne['scrutin']->inscriptions_count, 0, ',', ' ') }}</td>
                            <td class="nombre">{{ $ligne['scrutin']->candidates_actives_count }}</td>
                            <td>{{ $ligne['scrutin']->date_heure_ouverture?->timezone('Africa/Porto-Novo')->format('d/m/Y H:i') ?? 'À planifier' }}</td>
                            <td class="nombre">{{ $ligne['taux'] === null ? '-' : number_format($ligne['taux'], 1, ',', ' ').' %' }}</td>
                            <td><span class="badge {{ $ligne['scrutin']->statut->badge() }}">{{ $ligne['scrutin']->statut->libelle() }}</span></td>
                            <td class="actions">
                                @if ($onglet === 'resultats')
                                    <a wire:navigate href="{{ route('scrutins.show', [$ligne['scrutin'], 'onglet' => 'resultats']) }}" class="btn btn-tertiaire btn--sm"><i data-lucide="badge-check"></i>Valider</a>
                                @else
                                    <a wire:navigate href="{{ route('scrutins.show', $ligne['scrutin']) }}" class="btn btn-tertiaire btn--sm"><i data-lucide="eye"></i>Ouvrir</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><div class="etat-vide"><i data-lucide="clipboard-list"></i><p>{{ $onglet === 'resultats' ? 'Aucun résultat à valider.' : 'Aucun scrutin ne correspond.' }}</p></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $this->scrutins->onEachSide(1)->links('pagination.tresor-livewire', ['libelle' => 'scrutins']) }}
    @endif
</div>
