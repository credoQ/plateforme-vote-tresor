{{-- Onglets du référentiel : liste des structures, ou types de structure et départements. Seule la zone change. --}}
<div>
    <div class="carte espace-bloc">
        <nav class="onglets" aria-label="Rubriques du référentiel">
            @foreach (['structures' => 'Structures', 'organisation' => 'Types et départements'] as $cle => $libelleOnglet)
                <a href="{{ route('structures.index', ['onglet' => $cle]) }}" wire:click.prevent="choisirOnglet('{{ $cle }}')"
                   @class(['onglet', 'est-actif' => $onglet === $cle]) @if ($onglet === $cle) aria-current="page" @endif>{{ $libelleOnglet }}</a>
            @endforeach
        </nav>

        @if ($onglet === 'structures')
            <livewire:referentiel.liste-structures :key="'liste-structures'" />
        @else
            <div class="barre-filtres" id="types">
                <x-filtre libelle="Type de structure" pour="filtre-type" large>
                    <div class="champ-icone"><i data-lucide="search"></i><input id="filtre-type" type="search" class="champ" wire:model.live.debounce.400ms="rechercheType" placeholder="Libellé ou code"></div>
                </x-filtre>
                <x-filtre libelle="Pôle électoral" pour="filtre-pole">
                    <x-select-recherche compact wire:ignore>
                        <select id="filtre-pole" class="champ" wire:model.live="pole">
                            <option value="">Tous les pôles</option>
                            @foreach ($this->poles as $choix)
                                <option value="{{ $choix->code->value }}" @selected($pole === $choix->code->value)>{{ $choix->code->libelle() }}</option>
                            @endforeach
                        </select>
                    </x-select-recherche>
                </x-filtre>
                <x-fin-filtres :total="$this->types->count()" libelle="type(s)" :actifs="trim($rechercheType) !== '' || $pole !== ''" :reinitialiser="route('structures.index', ['onglet' => 'organisation'])" />
            </div>
            <div class="tableau-defilant" wire:loading.class="est-en-chargement">
                <table class="tableau tableau--fiches">
                    <thead><tr><th>Pôle électoral</th><th>Type de structure</th><th>Code</th><th class="nombre">Structures actives</th></tr></thead>
                    <tbody>
                        @forelse ($this->types as $ligne)
                            <tr wire:key="type-{{ $ligne['type']->id }}">
                                <td>{{ $ligne['pole']->code->libelle() }}</td>
                                <td>{{ $ligne['type']->code->libelle() }}</td>
                                <td class="reference">{{ $ligne['type']->code->value }}</td>
                                <td class="nombre"><a wire:navigate href="{{ route('structures.index', ['type' => $ligne['type']->id]) }}">{{ number_format($ligne['type']->structures_count, 0, ',', ' ') }}</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="4"><div class="etat-vide"><p>Aucun type de structure ne correspond aux filtres.</p></div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    @if ($onglet === 'organisation')
        <section class="carte" id="departements">
            <div class="carte__entete"><h2>Départements</h2></div>
            <div class="barre-filtres">
                <x-filtre libelle="Recherche" pour="filtre-departement" large>
                    <div class="champ-icone"><i data-lucide="search"></i><input id="filtre-departement" type="search" class="champ" wire:model.live.debounce.400ms="rechercheDepartement" placeholder="Code ou nom du département"></div>
                </x-filtre>
                <x-fin-filtres :total="$this->departements->count()" libelle="département(s)" :actifs="trim($rechercheDepartement) !== ''" :reinitialiser="route('structures.index', ['onglet' => 'organisation'])" />
            </div>
            <div class="tableau-defilant" wire:loading.class="est-en-chargement">
                <table class="tableau tableau--fiches">
                    <thead><tr><th>Code</th><th>Département</th><th>Trésorerie départementale</th><th class="nombre">Trésoreries communales</th></tr></thead>
                    <tbody>
                        @forelse ($this->departements as $departement)
                            <tr wire:key="departement-{{ $departement->id }}">
                                <td class="reference">{{ $departement->code }}</td>
                                <td>{{ $departement->libelle }}</td>
                                <td>
                                    @if ($departement->tresorerieDepartementale)
                                        <a wire:navigate href="{{ route('structures.show', $departement->tresorerieDepartementale) }}">{{ $departement->tresorerieDepartementale->code }}</a>
                                    @else
                                        <span class="badge badge--attente">Aucune</span>
                                    @endif
                                </td>
                                <td class="nombre"><a wire:navigate href="{{ route('structures.index', ['departement' => $departement->id]) }}">{{ $departement->tresoreries_communales_count }}</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="4"><div class="etat-vide"><p>Aucun département ne correspond à la recherche.</p></div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endif
</div>
