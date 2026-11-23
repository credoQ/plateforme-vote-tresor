<x-layouts.app titre="Types et départements" :ariane="['Administration', 'Structures', 'Types et départements']" actif="structures">
    <div class="titre-page">
        <div>
            <h1>Structures</h1>
        </div>
    </div>

    <div class="carte espace-bloc">
        <div class="onglets">
            <a wire:navigate href="{{ route('structures.index') }}" class="onglet">Structures</a>
            <a wire:navigate href="{{ route('structures.organisation') }}" class="onglet est-actif">Types et départements</a>
        </div>
        <x-barre-filtres :total="$types->count()" libelle="type(s)" :action="route('structures.organisation').'#types'" :conserver="['departement' => $filtres['departement']]" id="types">
            <x-filtre libelle="Type de structure" pour="filtre-type" large>
                <div class="champ-icone"><i data-lucide="search"></i><input id="filtre-type" type="search" name="type" class="champ" value="{{ $filtres['type'] }}" placeholder="Libellé ou code"></div>
            </x-filtre>
            <x-filtre libelle="Pôle électoral" pour="filtre-pole">
                <x-select-recherche compact soumettre>
                    <select id="filtre-pole" name="pole" class="champ">
                        <option value="">Tous les pôles</option>
                        @foreach ($poles as $pole)
                            <option value="{{ $pole->code->value }}" @selected($filtres['pole'] === $pole->code->value)>{{ $pole->code->libelle() }}</option>
                        @endforeach
                    </select>
                </x-select-recherche>
            </x-filtre>
        </x-barre-filtres>
        <div class="tableau-defilant">
            <table class="tableau tableau--fiches">
                <thead><tr><th>Pôle électoral</th><th>Type de structure</th><th>Code</th><th class="nombre">Structures actives</th></tr></thead>
                <tbody>
                    @forelse ($types as $ligne)
                        <tr>
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
    </div>

    <section class="carte" id="departements">
        <div class="carte__entete"><h2>Départements</h2></div>
        <x-barre-filtres :total="$departements->count()" libelle="département(s)" :action="route('structures.organisation').'#departements'" :conserver="['type' => $filtres['type'], 'pole' => $filtres['pole']]">
            <x-filtre libelle="Recherche" pour="filtre-departement" large>
                <div class="champ-icone"><i data-lucide="search"></i><input id="filtre-departement" type="search" name="departement" class="champ" value="{{ $filtres['departement'] }}" placeholder="Code ou nom du département"></div>
            </x-filtre>
        </x-barre-filtres>
        <div class="tableau-defilant">
            <table class="tableau tableau--fiches">
                <thead><tr><th>Code</th><th>Département</th><th>Trésorerie départementale</th><th class="nombre">Trésoreries communales</th></tr></thead>
                <tbody>
                    @forelse ($departements as $departement)
                        <tr>
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
</x-layouts.app>
