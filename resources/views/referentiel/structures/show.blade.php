<x-layouts.app :titre="$structure->libelle" :ariane="['Administration', 'Structures', $structure->code]" actif="structures">
    <div class="titre-page">
        <div>
            <div class="surtitre">{{ $structure->code }}</div>
            <h1>{{ $structure->libelle }}</h1>
            <p>
                @if ($structure->actif)
                    <span class="badge badge--succes">Active</span>
                @else
                    <span class="badge badge--erreur">Désactivée</span>
                @endif
            </p>
        </div>
        <div class="titre-page__actions">
            @can('update', $structure)
                <a href="{{ route('structures.edit', $structure) }}" class="btn btn-secondaire"><i data-lucide="pencil"></i>Modifier</a>
                @if ($structure->actif)
                    <button type="button" class="btn btn-danger-contour" data-ouvrir="modale-desactiver"><i data-lucide="ban"></i>Désactiver</button>
                @else
                    <button type="button" class="btn btn-secondaire" data-ouvrir="modale-reactiver"><i data-lucide="rotate-ccw"></i>Réactiver</button>
                @endif
            @endcan
            <a href="{{ route('structures.index') }}" class="btn btn-tertiaire"><i data-lucide="arrow-left"></i>Retour à la liste</a>
        </div>
    </div>

    <div class="grille-deux espace-bloc">
        <section class="carte">
            <div class="carte__entete"><h2>Fiche</h2></div>
            <div class="tableau-defilant">
                <table class="tableau">
                    <tbody>
                        <tr><td>Sigle</td><td>{{ $structure->sigle }}</td></tr>
                        <tr><td>Type</td><td>{{ $structure->typeStructure->code->libelle() }}</td></tr>
                        <tr><td>Pôle électoral</td><td>{{ $structure->typeStructure->poleElectoral->code->libelle() }}</td></tr>
                        <tr><td>Niveau</td><td>{{ $structure->estUnService() ? 'Service rattaché' : 'Structure électorale' }}</td></tr>
                        <tr>
                            <td>Rattachée à</td>
                            <td>
                                @if ($structure->structureParente)
                                    <a href="{{ route('structures.show', $structure->structureParente) }}">{{ $structure->structureParente->code }}</a> · {{ $structure->structureParente->libelle }}
                                @else
                                    Aucune
                                @endif
                            </td>
                        </tr>
                        <tr><td>Département</td><td>{{ $structure->departement?->libelle ?? 'Aucun' }}</td></tr>
                        <tr>
                            <td>Agents en service</td>
                            <td class="chiffres">
                                @can('viewAny', \App\Models\Agent::class)
                                    <a href="{{ route('agents.index', ['structure' => $structure->id]) }}">{{ number_format($agentsEnService, 0, ',', ' ') }}</a>
                                @else
                                    {{ number_format($agentsEnService, 0, ',', ' ') }}
                                @endcan
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="carte">
            <div class="carte__entete">
                <h2>Structures rattachées</h2>
                @can('update', $structure)
                    @if ($structure->actif && ! $structure->estUnService())
                        <a href="{{ route('structures.create', ['parente' => $structure->id]) }}" class="btn btn-tertiaire btn--sm"><i data-lucide="plus"></i>Ajouter un service</a>
                    @endif
                @endcan
            </div>
            <div class="tableau-defilant">
                <table class="tableau">
                    <thead><tr><th>Code</th><th>Structure</th><th class="nombre">Agents</th><th>Statut</th></tr></thead>
                    <tbody>
                        @forelse ($structuresFilles as $fille)
                            <tr>
                                <td class="reference"><a href="{{ route('structures.show', $fille) }}">{{ $fille->code }}</a></td>
                                <td>{{ $fille->libelle }}<div class="aide">{{ $fille->estUnService() ? 'Service' : $fille->typeStructure->code->libelle() }}</div></td>
                                <td class="nombre">{{ number_format($fille->agents_en_service_count, 0, ',', ' ') }}</td>
                                <td>
                                    @if ($fille->actif)
                                        <span class="badge badge--succes">Active</span>
                                    @else
                                        <span class="badge badge--erreur">Désactivée</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4"><div class="etat-vide"><p>Aucune structure rattachée.</p></div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <x-slot:modales>
        @can('update', $structure)
            @if ($structure->actif)
                <x-modale-confirmation id="modale-desactiver" :titre="'Désactiver '.$structure->code.' ?'" :action="route('structures.desactiver', $structure)" bouton="Désactiver la structure" icone="ban" :danger="true">
                    {{ $structure->libelle }} ne pourra plus recevoir d’affectation et ne sera plus retenue à la génération des scrutins. Elle reste dans l’historique des affectations et peut être réactivée.
                    <x-slot:champs>
                        <label class="libelle" for="motif">Motif</label>
                        <textarea id="motif" name="motif" class="champ" maxlength="500"></textarea>
                    </x-slot:champs>
                </x-modale-confirmation>
            @else
                <x-modale-confirmation id="modale-reactiver" :titre="'Réactiver '.$structure->code.' ?'" :action="route('structures.reactiver', $structure)" bouton="Réactiver la structure" icone="rotate-ccw">
                    {{ $structure->libelle }} pourra de nouveau recevoir des affectations.
                </x-modale-confirmation>
            @endif
        @endcan
    </x-slot:modales>
</x-layouts.app>
