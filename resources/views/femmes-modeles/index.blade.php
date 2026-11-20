<x-layouts.app titre="Femmes modèles" :ariane="['Pilotage', 'Femmes modèles']" actif="femmes-modeles">
    <div class="titre-page">
        <div>
            <h1>Femmes modèles du Trésor</h1>
            @if ($edition)
                <p>Édition {{ $edition->annee }} : délai de rééligibilité de {{ $edition->delai_reeligibilite }} an(s).</p>
            @endif
        </div>
        <div class="titre-page__actions">
            <a href="{{ route('femmes-modeles.create') }}" class="btn btn-primaire" wire:navigate><i data-lucide="user-plus"></i>Ajouter une femme modèle</a>
        </div>
    </div>

    @error('femme_modele')<div class="alerte alerte--erreur espace-bloc" role="alert"><i data-lucide="circle-alert"></i><div>{{ $message }}</div></div>@enderror

    <section class="carte">
        <div class="carte__entete"><h2>Historique</h2></div>
        <x-barre-filtres :total="$femmesModeles->total()" libelle="femme(s) modèle(s)">
            <x-filtre libelle="Recherche" pour="filtre-recherche" large>
                <div class="champ-icone"><i data-lucide="search"></i><input id="filtre-recherche" type="search" name="recherche" class="champ" value="{{ $recherche }}" placeholder="Nom ou matricule"></div>
            </x-filtre>
            <x-filtre libelle="Année" pour="filtre-annee">
                <x-select-recherche compact soumettre>
                    <select id="filtre-annee" name="annee" class="champ">
                        <option value="">Toutes les années</option>
                        @foreach ($annees as $valeur)
                            <option value="{{ $valeur }}" @selected($annee === $valeur)>{{ $valeur }}</option>
                        @endforeach
                    </select>
                </x-select-recherche>
            </x-filtre>
        </x-barre-filtres>
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
                                @if ($femmeModele->source->estRetirable())
                                    <button type="button" class="btn btn-tertiaire btn--sm" data-ouvrir="modale-supprimer-{{ $femmeModele->id }}"><i data-lucide="trash-2"></i>Retirer</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><div class="etat-vide"><i data-lucide="crown"></i><p>Aucune femme modèle enregistrée. Ajoutez celles des éditions antérieures.</p></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $femmesModeles->onEachSide(1)->links('pagination.tresor', ['libelle' => 'femmes modèles']) }}
    </section>

    <x-slot:modales>
        @foreach ($femmesModeles as $femmeModele)
            @if ($femmeModele->source->estRetirable())
                <x-modale-confirmation :id="'modale-supprimer-'.$femmeModele->id" :titre="'Retirer '.$femmeModele->nom_complet.' ('.$femmeModele->annee.') de l’historique ?'" :action="route('femmes-modeles.destroy', $femmeModele)" bouton="Retirer de l’historique" icone="trash-2" :danger="true">
                    Cette ligne ne comptera plus pour l’inéligibilité. Le retrait est tracé au journal.
                </x-modale-confirmation>
            @endif
        @endforeach
    </x-slot:modales>
</x-layouts.app>
