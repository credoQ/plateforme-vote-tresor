{{-- Historique des femmes modèles : recherche, année et pagination sans recharger la page. Les fenêtres de retrait
     suivent les lignes affichées. --}}
<div>
    <div class="barre-filtres">
        <x-filtre libelle="Recherche" pour="filtre-recherche" large>
            <div class="champ-icone"><i data-lucide="search"></i><input id="filtre-recherche" type="search" class="champ" wire:model.live.debounce.400ms="recherche" placeholder="Nom ou matricule"></div>
        </x-filtre>
        <x-filtre libelle="Année" pour="filtre-annee">
            <x-select-recherche compact wire:ignore>
                <select id="filtre-annee" class="champ" wire:model.live="annee">
                    <option value="">Toutes les années</option>
                    @foreach ($this->annees as $valeur)
                        <option value="{{ $valeur }}" @selected($annee === (string) $valeur)>{{ $valeur }}</option>
                    @endforeach
                </select>
            </x-select-recherche>
        </x-filtre>
        <x-fin-filtres :total="$this->femmesModeles->total()" libelle="femme(s) modèle(s)" :actifs="$filtresActifs" :reinitialiser="route('femmes-modeles.index')" />
    </div>
    <div class="tableau-defilant" wire:loading.class="est-en-chargement">
        <table class="tableau tableau--fiches">
            <thead><tr><th class="nombre">Année</th><th>Femme modèle</th><th>Matricule</th><th>Scrutin</th><th>Source</th><th>Éligibilité</th><th class="actions">Actions</th></tr></thead>
            <tbody>
                @forelse ($this->femmesModeles as $femmeModele)
                    <tr wire:key="femme-modele-{{ $femmeModele->id }}">
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
    {{ $this->femmesModeles->onEachSide(1)->links('pagination.tresor-livewire', ['libelle' => 'femmes modèles']) }}
    <div hidden>
        @foreach ($this->femmesModeles as $femmeModele)
            @if ($femmeModele->source->estRetirable())
                <x-modale-confirmation :id="'modale-supprimer-'.$femmeModele->id" :titre="'Retirer '.$femmeModele->nom_complet.' ('.$femmeModele->annee.') de l’historique ?'" :action="route('femmes-modeles.destroy', $femmeModele)" bouton="Retirer de l’historique" icone="trash-2" :danger="true">
                    Cette ligne ne comptera plus pour l’inéligibilité. Le retrait est tracé au journal.
                </x-modale-confirmation>
            @endif
        @endforeach
    </div>
</div>
