@use('App\Enums\Editions\SourceFemmeModele')
<x-layouts.app titre="Femmes modèles" :ariane="['Pilotage', 'Femmes modèles']" actif="femmes-modeles">
    <div class="titre-page">
        <div>
            <h1>Femmes modèles du Trésor</h1>
            @if ($edition)
                <p>Édition {{ $edition->annee }} : délai de rééligibilité de {{ $edition->delai_reeligibilite }} an(s).</p>
            @endif
        </div>
    </div>

    @error('femme_modele')<div class="alerte alerte--erreur espace-bloc" role="alert"><i data-lucide="circle-alert"></i><div>{{ $message }}</div></div>@enderror
    @if (session('rejets'))
        <div class="alerte alerte--attente espace-bloc" role="status">
            <i data-lucide="triangle-alert"></i>
            <div>
                <strong>{{ count(session('rejets')) }} ligne(s) rejetée(s)</strong>
                <ul>
                    @foreach (session('rejets') as $ligne => $motif)
                        <li>Ligne {{ $ligne }} : {{ $motif }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    <section class="carte espace-bloc">
        <div class="carte__entete"><h2>Importer les éditions antérieures</h2></div>
        <div class="carte__corps">
            <form method="POST" action="{{ route('femmes-modeles.importer') }}" enctype="multipart/form-data" class="formulaire-ligne">
                @csrf
                <div class="formulaire__groupe formulaire__groupe--large">
                    <label class="libelle" for="fichier">Fichier Excel ou CSV</label>
                    <input id="fichier" name="fichier" type="file" class="champ" accept=".xlsx,.csv,.txt" required>
                    <p class="aide">Colonnes : matricule et annee ; nom et scrutin facultatifs.</p>
                    @error('fichier')<p class="erreur-champ">{{ $message }}</p>@enderror
                </div>
                <button type="submit" class="btn btn-secondaire"><i data-lucide="upload"></i>Importer le fichier</button>
            </form>
        </div>
    </section>

    <section class="carte">
        <div class="carte__entete"><h2>Historique</h2><span class="etiquette">{{ $femmesModeles->count() }} femme(s) modèle(s)</span></div>
        <div class="tableau-defilant">
            <table class="tableau tableau--fiches">
                <thead><tr><th class="nombre">Année</th><th>Femme modèle</th><th>Matricule</th><th>Scrutin</th><th>Source</th><th>Éligibilité</th><th class="actions">Actions</th></tr></thead>
                <tbody>
                    @forelse ($femmesModeles as $femmeModele)
                        <tr>
                            <td class="nombre">{{ $femmeModele->annee }}</td>
                            <td>{{ $femmeModele->nom_complet }}</td>
                            <td class="reference">{{ $femmeModele->matricule }}</td>
                            <td>{{ $femmeModele->scrutin }}</td>
                            <td>{{ $femmeModele->source->libelle() }}</td>
                            <td>
                                @if ($edition && ($ineligibles[$femmeModele->matricule] ?? null) === $femmeModele->annee)
                                    <span class="badge badge--attente">Inéligible jusqu’en {{ $edition->derniereAnneeIneligible($femmeModele->annee) }}</span>
                                @endif
                            </td>
                            <td class="actions">
                                @if ($femmeModele->source === SourceFemmeModele::Import)
                                    <button type="button" class="btn btn-tertiaire btn--sm" data-ouvrir="modale-supprimer-{{ $femmeModele->id }}"><i data-lucide="trash-2"></i>Retirer</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><div class="etat-vide"><i data-lucide="crown"></i><p>Aucune femme modèle enregistrée. Importez celles des éditions antérieures.</p></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <x-slot:modales>
        @foreach ($femmesModeles->where('source', SourceFemmeModele::Import) as $femmeModele)
            <x-modale-confirmation :id="'modale-supprimer-'.$femmeModele->id" :titre="'Retirer '.$femmeModele->nom_complet.' ('.$femmeModele->annee.') de l’historique ?'" :action="route('femmes-modeles.destroy', $femmeModele)" bouton="Retirer de l’historique" icone="trash-2" :danger="true">
                Cette ligne importée ne comptera plus pour l’inéligibilité. Le retrait est tracé au journal.
            </x-modale-confirmation>
        @endforeach
    </x-slot:modales>
</x-layouts.app>
