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
        <x-barre-filtres :total="$agents->total()" libelle="agent(s)">
            <x-filtre libelle="Recherche" pour="filtre-recherche" large>
                <div class="champ-icone"><i data-lucide="search"></i><input id="filtre-recherche" type="search" name="recherche" class="champ" value="{{ $recherche }}" placeholder="Matricule, NPI ou nom"></div>
            </x-filtre>
            <x-filtre libelle="Structure" pour="filtre-structure">
                <x-select-recherche compact soumettre>
                    <select id="filtre-structure" name="structure" class="champ">
                        <option value="">Toutes les structures</option>
                        @foreach ($structures as $structure)
                            <option value="{{ $structure->id }}" data-detail="{{ $structure->libelle }}" @selected($structureId === $structure->id)>{{ $structure->code }}</option>
                        @endforeach
                    </select>
                </x-select-recherche>
            </x-filtre>
            <x-filtre libelle="Sexe" pour="filtre-sexe">
                <x-select-recherche compact soumettre>
                    <select id="filtre-sexe" name="sexe" class="champ">
                        <option value="">Tous</option>
                        @foreach (\App\Enums\Agents\Sexe::cases() as $cas)
                            <option value="{{ $cas->value }}" @selected($sexe === $cas)>{{ $cas->libelle() }}</option>
                        @endforeach
                    </select>
                </x-select-recherche>
            </x-filtre>
            <x-filtre libelle="Situation" pour="filtre-situation">
                <x-select-recherche compact soumettre>
                    <select id="filtre-situation" name="situation" class="champ">
                        <option value="en-service" @selected($situation === 'en-service')>En service</option>
                        @unless ($restreint)<option value="hors-service" @selected($situation === 'hors-service')>Sans affectation en cours</option>@endunless
                        <option value="tous" @selected($situation === 'tous')>Tous</option>
                    </select>
                </x-select-recherche>
            </x-filtre>
        </x-barre-filtres>

        <div class="tableau-defilant">
            <table class="tableau tableau--fiches">
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
        {{ $agents->onEachSide(1)->links('pagination.tresor', ['libelle' => 'agents']) }}
    </div>
</x-layouts.app>
