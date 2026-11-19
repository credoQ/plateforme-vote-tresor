<x-layouts.app titre="Agents" :ariane="['Administration', 'Agents']" actif="agents">
    <div class="titre-page">
        <div>
            <h1>{{ $restreint ? 'Agents de ma structure' : 'Agents' }}</h1>
        </div>
        @can('create', \App\Models\Agent::class)
            <div class="titre-page__actions">
                <a href="{{ route('agents.create') }}" class="btn btn-primaire"><i data-lucide="user-plus"></i>Saisir un agent</a>
            </div>
        @endcan
    </div>

    <div class="carte">
        <form method="GET" class="barre-filtres">
            <div class="champ-icone"><i data-lucide="search"></i><input name="recherche" class="champ" value="{{ $recherche }}" placeholder="Matricule, NPI ou nom" aria-label="Rechercher un agent"></div>
            <select name="structure" class="champ" aria-label="Filtrer par structure">
                <option value="">Toutes les structures</option>
                @foreach ($structures as $structure)
                    <option value="{{ $structure->id }}" @selected($structureId === $structure->id)>{{ $structure->code }}</option>
                @endforeach
            </select>
            <select name="sexe" class="champ" aria-label="Filtrer par sexe">
                <option value="">Tous</option>
                @foreach (\App\Enums\Agents\Sexe::cases() as $cas)
                    <option value="{{ $cas->value }}" @selected($sexe === $cas)>{{ $cas->libelle() }}</option>
                @endforeach
            </select>
            <select name="situation" class="champ" aria-label="Filtrer par situation">
                <option value="en-service" @selected($situation === 'en-service')>En service</option>
                @unless ($restreint)<option value="hors-service" @selected($situation === 'hors-service')>Sans affectation en cours</option>@endunless
                <option value="tous" @selected($situation === 'tous')>Tous</option>
            </select>
            <button type="submit" class="btn btn-secondaire"><i data-lucide="filter"></i>Filtrer</button>
            <span class="aide"><b class="chiffres">{{ number_format($agents->total(), 0, ',', ' ') }}</b> agent(s)</span>
        </form>

        <div class="tableau-defilant">
            <table class="tableau">
                <thead><tr><th>Matricule</th><th>Agent</th><th>Sexe</th><th>Affectation en cours</th><th>Depuis</th><th class="actions"></th></tr></thead>
                <tbody>
                    @forelse ($agents as $agent)
                        <tr>
                            <td class="reference">{{ $agent->matricule }}</td>
                            <td>{{ $agent->nomComplet() }}@if ($agent->npi)<div class="aide">NPI {{ $agent->npi }}</div>@endif</td>
                            <td>{{ $agent->sexe->libelle() }}</td>
                            <td>
                                @if ($agent->affectationEnCours)
                                    {{ $agent->affectationEnCours->structure->code }}<div class="aide">{{ $agent->affectationEnCours->structure->libelle }}</div>
                                @else
                                    <span class="badge badge--attente">Sans affectation</span>
                                @endif
                            </td>
                            <td>{{ $agent->affectationEnCours?->date_debut->format('d/m/Y') }}</td>
                            <td class="actions"><a href="{{ route('agents.show', $agent) }}" class="btn btn-tertiaire btn--sm"><i data-lucide="eye"></i>Fiche</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><div class="etat-vide"><i data-lucide="id-card"></i><p>Aucun agent ne correspond aux filtres.</p></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $agents->links() }}
    </div>
</x-layouts.app>
