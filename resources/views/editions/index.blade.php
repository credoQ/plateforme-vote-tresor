<x-layouts.app titre="Éditions" :ariane="['Pilotage', 'Éditions']" actif="editions">
    <div class="titre-page">
        <div>
            <h1>Éditions</h1>
        </div>
        <div class="titre-page__actions">
            <a href="{{ route('editions.create') }}" class="btn btn-primaire"><i data-lucide="plus"></i>Créer une édition</a>
        </div>
    </div>

    <div class="carte">
        <x-barre-filtres :total="$editions->total()" libelle="édition(s)">
            <x-filtre libelle="Recherche" pour="filtre-recherche" large>
                <div class="champ-icone"><i data-lucide="search"></i><input id="filtre-recherche" type="search" name="recherche" class="champ" value="{{ $recherche }}" placeholder="Année ou libellé"></div>
            </x-filtre>
            <x-filtre libelle="Statut" pour="filtre-statut">
                <x-select-recherche compact soumettre>
                    <select id="filtre-statut" name="statut" class="champ">
                        <option value="">Tous les statuts</option>
                        @foreach (\App\Enums\Editions\StatutEdition::cases() as $cas)
                            <option value="{{ $cas->value }}" @selected($statut === $cas)>{{ $cas->libelle() }}</option>
                        @endforeach
                    </select>
                </x-select-recherche>
            </x-filtre>
        </x-barre-filtres>
        <div class="tableau-defilant">
            <table class="tableau tableau--fiches">
                <thead><tr><th>Année</th><th>Libellé</th><th>Calendrier</th><th>Gel des listes</th><th class="nombre">Scrutins</th><th>Statut</th><th class="actions"></th></tr></thead>
                <tbody>
                    @forelse ($editions as $edition)
                        <tr>
                            <td class="reference">{{ $edition->annee }}</td>
                            <td>{{ $edition->libelle }}</td>
                            <td>du {{ $edition->date_debut->format('d/m/Y') }} au {{ $edition->date_fin->format('d/m/Y') }}</td>
                            <td>{{ $edition->date_gel->format('d/m/Y') }}</td>
                            <td class="nombre">{{ $edition->scrutins_count }}</td>
                            <td><span class="badge {{ $edition->statut->badge() }}">{{ $edition->statut->libelle() }}</span></td>
                            <td class="actions"><a href="{{ route('editions.show', $edition) }}" class="btn btn-tertiaire btn--sm"><i data-lucide="eye"></i>Ouvrir</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><div class="etat-vide"><i data-lucide="calendar-plus"></i><p>Aucune édition.</p></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $editions->onEachSide(1)->links('pagination.tresor', ['libelle' => 'éditions']) }}
    </div>
</x-layouts.app>
