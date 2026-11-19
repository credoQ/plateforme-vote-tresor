<x-layouts.app titre="Structures" :ariane="['Administration', 'Structures']" actif="structures">
    <div class="titre-page">
        <div>
            <h1>Structures</h1>
        </div>
        @can('create', \App\Models\Structure::class)
            <div class="titre-page__actions">
                <a href="{{ route('structures.create') }}" class="btn btn-primaire"><i data-lucide="plus"></i>Créer une structure</a>
            </div>
        @endcan
    </div>

    <div class="carte">
        <div class="onglets">
            <a href="{{ route('structures.index') }}" class="onglet est-actif">Structures</a>
            <a href="{{ route('structures.organisation') }}" class="onglet">Types et départements</a>
        </div>
        <form method="GET" class="barre-filtres">
            <div class="champ-icone"><i data-lucide="search"></i><input name="recherche" class="champ" value="{{ $recherche }}" placeholder="Code, sigle ou libellé" aria-label="Rechercher une structure"></div>
            <select name="type" class="champ" aria-label="Filtrer par type">
                <option value="">Tous les types</option>
                @foreach ($types as $type)
                    <option value="{{ $type->id }}" @selected($typeId === $type->id)>{{ $type->code->libelle() }}</option>
                @endforeach
            </select>
            <select name="departement" class="champ" aria-label="Filtrer par département">
                <option value="">Tous les départements</option>
                @foreach ($departements as $departement)
                    <option value="{{ $departement->id }}" @selected($departementId === $departement->id)>{{ $departement->libelle }}</option>
                @endforeach
            </select>
            <select name="statut" class="champ" aria-label="Filtrer par statut">
                <option value="actives" @selected($statut === 'actives')>Actives</option>
                <option value="desactivees" @selected($statut === 'desactivees')>Désactivées</option>
                <option value="toutes" @selected($statut === 'toutes')>Toutes</option>
            </select>
            <button type="submit" class="btn btn-secondaire"><i data-lucide="filter"></i>Filtrer</button>
            <span class="aide"><b class="chiffres">{{ number_format($structures->total(), 0, ',', ' ') }}</b> structure(s)</span>
        </form>

        <div class="tableau-defilant">
            <table class="tableau">
                <thead><tr><th>Code</th><th>Structure</th><th>Type</th><th>Rattachement</th><th class="nombre">Agents en service</th><th>Statut</th><th class="actions"></th></tr></thead>
                <tbody>
                    @forelse ($structures as $structure)
                        <tr>
                            <td class="reference">{{ $structure->code }}</td>
                            <td>{{ $structure->libelle }}@if ($structure->estUnService())<div class="aide">Service</div>@endif</td>
                            <td>{{ $structure->typeStructure->code->libelle() }}@if ($structure->departement)<div class="aide">{{ $structure->departement->libelle }}</div>@endif</td>
                            <td>{{ $structure->structureParente?->code }}</td>
                            <td class="nombre">{{ number_format($structure->agents_en_service_count, 0, ',', ' ') }}</td>
                            <td>
                                @if ($structure->actif)
                                    <span class="badge badge--succes">Active</span>
                                @else
                                    <span class="badge badge--erreur">Désactivée</span>
                                @endif
                            </td>
                            <td class="actions"><a href="{{ route('structures.show', $structure) }}" class="btn btn-tertiaire btn--sm"><i data-lucide="eye"></i>Fiche</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><div class="etat-vide"><i data-lucide="building-2"></i><p>Aucune structure ne correspond aux filtres.</p></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $structures->links() }}
    </div>
</x-layouts.app>
