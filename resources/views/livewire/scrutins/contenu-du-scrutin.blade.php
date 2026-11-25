@use('App\Enums\Scrutins\OrigineCandidate')
{{-- Onglets de la fiche d'un scrutin : seule cette zone est mise à jour au changement d'onglet, de filtre ou de
     page. Les fenêtres de confirmation des candidates affichées suivent le contenu de la zone. --}}
<div>
        <nav class="onglets" aria-label="Rubriques du scrutin">
            @foreach ($onglets as $cle => $libelleOnglet)
                <a href="{{ route('scrutins.show', [$scrutin, 'onglet' => $cle]) }}" wire:click.prevent="choisirOnglet('{{ $cle }}')" @class(['onglet', 'est-actif' => $onglet === $cle]) @if ($onglet === $cle) aria-current="page" @endif>{{ $libelleOnglet['libelle'] }}@if ($libelleOnglet['compteur'] !== null) <span class="compteur">{{ $libelleOnglet['compteur'] }}</span>@endif</a>
            @endforeach
        </nav>

    @if ($onglet === 'resultats')
        @include('resultats._scrutin')
    @endif

    @if ($onglet === 'candidates')
    <div id="candidates">
        <div class="carte__bandeau"><span class="etiquette">{{ $scrutin->college->origine_candidates->libelleCollege() }} · {{ $scrutin->nbElues() }} élue(s)</span></div>
        @if ($ineligiblesEcartees->isNotEmpty())
            <div class="carte__corps">
                <div class="alerte alerte--info"><i data-lucide="crown"></i><div><strong>Inéligibles</strong>, électrices mais pas candidates : {{ $ineligiblesEcartees->implode(', ') }}.</div></div>
            </div>
        @endif
        @if ($nominationOuverte)
            <div class="carte__corps">
                <form method="POST" action="{{ route('nominations.store', $scrutin) }}" class="formulaire-ligne">
                    @csrf
                    <div class="formulaire__groupe formulaire__groupe--large">
                        <label class="libelle" for="matricules-nominees">Candidates</label>
                        <x-select-recherche placeholder="Nom ou matricule">
                            <select id="matricules-nominees" name="matricules[]" class="champ" multiple required>
                                @foreach ($nominables as $electrice)
                                    <option value="{{ $electrice->matricule }}" data-detail="{{ $electrice->matricule }}" @selected(in_array($electrice->matricule, (array) old('matricules', []), true))>{{ $electrice->nomComplet() }}</option>
                                @endforeach
                            </select>
                        </x-select-recherche>
                        @error('matricules')<p class="erreur-champ">{{ $message }}</p>@enderror
                        @error('matricule')<p class="erreur-champ">{{ $message }}</p>@enderror
                    </div>
                    <div class="formulaire__groupe formulaire__groupe--large">
                        <label class="libelle" for="reference-nomination">Référence de la décision</label>
                        <input id="reference-nomination" name="reference_nomination" class="champ" maxlength="200" value="{{ old('reference_nomination') }}" placeholder="Ex. : PV du CODIR du 25/11/2026" required>
                        @error('reference_nomination')<p class="erreur-champ">{{ $message }}</p>@enderror
                    </div>
                    <button type="submit" class="btn btn-secondaire"><i data-lucide="user-plus"></i>Nominer les candidates</button>
                </form>
            </div>
        @endif
        <div class="barre-filtres">
            <x-filtre libelle="Recherche" pour="filtre-candidate" large>
                <div class="champ-icone"><i data-lucide="search"></i><input id="filtre-candidate" type="search" class="champ" wire:model.live.debounce.400ms="rechercheCandidate" placeholder="Nom ou matricule"></div>
            </x-filtre>
            @if ($structures->count() > 1)
                <x-filtre libelle="Structure" pour="filtre-structure-candidate">
                    <x-select-recherche compact wire:ignore>
                        <select id="filtre-structure-candidate" class="champ" wire:model.live="structureCandidate">
                            <option value="">Toutes les structures</option>
                            @foreach ($structures as $structure)
                                <option value="{{ $structure->id }}" data-detail="{{ $structure->libelle }}" @selected($structureCandidate === (string) $structure->id)>{{ $structure->code }}</option>
                            @endforeach
                        </select>
                    </x-select-recherche>
                </x-filtre>
            @endif
            <x-filtre libelle="Statut" pour="filtre-statut-candidate">
                <x-select-recherche compact wire:ignore>
                    <select id="filtre-statut-candidate" class="champ" wire:model.live="statutCandidate">
                        <option value="">Tous les statuts</option>
                        @foreach (\App\Enums\Scrutins\StatutCandidate::cases() as $cas)
                            <option value="{{ $cas->value }}" @selected($statutCandidate === $cas->value)>{{ $cas->libelle() }}</option>
                        @endforeach
                    </select>
                </x-select-recherche>
            </x-filtre>
            <x-fin-filtres :total="$this->pageCandidates->total()" libelle="candidate(s)" :actifs="$filtresCandidatesActifs" :reinitialiser="route('scrutins.show', [$scrutin, 'onglet' => 'candidates'])" />
        </div>
        <div class="tableau-defilant" wire:loading.class="est-en-chargement">
            <table class="tableau tableau--fiches">
                <thead><tr><th>Candidate</th><th>Structure</th><th>Origine</th><th>Statut</th><th class="actions">Actions</th></tr></thead>
                <tbody>
                    @forelse ($this->pageCandidates as $candidate)
                        <tr wire:key="candidate-{{ $candidate->id }}">
                            <td>
                                <div class="cellule-photo">
                                    @if ($candidate->photo)
                                        <img class="vignette-photo" src="{{ $candidate->urlPhoto() }}" alt="" loading="lazy">
                                    @else
                                        <span class="vignette-photo"><x-avatar-femme :variante="$candidate->agent->avatar()" /></span>
                                    @endif
                                    <div>{{ $candidate->agent->nomComplet() }}<div class="aide">{{ $candidate->agent->matricule }}</div></div>
                                </div>
                            </td>
                            <td>{{ $structures[$candidate->structure_inscription_id]->code ?? '' }}</td>
                            <td>
                                {{ $candidate->origine->libelle() }}
                                @if ($candidate->reference_nomination)
                                    <div class="aide">{{ $candidate->reference_nomination }}{{ $candidate->nommeePar ? ' · saisie par '.$candidate->nommeePar->name : '' }}</div>
                                @endif
                            </td>
                            <td>
                                @if ($candidate->estActive())
                                    <span class="badge badge--succes">Active</span>
                                @else
                                    <span class="badge badge--erreur">Désactivée</span>
                                    <div class="aide">le {{ $candidate->date_desistement->timezone($fuseau)->format('d/m/Y') }}{{ $candidate->desactiveePar ? ' par '.$candidate->desactiveePar->name : '' }}{{ $candidate->motif_desistement ? ' · '.$candidate->motif_desistement : '' }}</div>
                                @endif
                            </td>
                            <td class="actions">
                                @if ($nominationOuverte && $candidate->origine === OrigineCandidate::Nomination)
                                    <button type="button" class="btn btn-tertiaire btn--sm" data-ouvrir="modale-retirer-nomination-{{ $candidate->id }}"><i data-lucide="user-minus"></i>Retirer la nomination</button>
                                @endif
                                @if ($scrutin->accepteLesDesistements())
                                    @can('changerPhoto', $candidate)
                                        <button type="button" class="btn btn-tertiaire btn--sm" x-on:click="Livewire.dispatch('ouvrir-photo-candidate', { candidate: {{ $candidate->id }} })"><i data-lucide="{{ $candidate->photo ? 'image' : 'image-plus' }}"></i>{{ $candidate->photo ? 'Modifier la photo' : 'Ajouter une photo' }}</button>
                                    @endcan
                                    @if ($candidate->estActive())
                                        @can('desactiver', $candidate)
                                            <button type="button" class="btn btn-danger-contour btn--sm" data-ouvrir="modale-desactiver-{{ $candidate->id }}"><i data-lucide="user-x"></i>Désactiver</button>
                                        @endcan
                                    @else
                                        @can('reactiver', $candidate)
                                            <form method="POST" action="{{ route('candidatures.reactiver', $candidate) }}">
                                                @csrf
                                                <button type="submit" class="btn btn-tertiaire btn--sm"><i data-lucide="user-check"></i>Réactiver</button>
                                            </form>
                                        @endcan
                                    @endif
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><div class="etat-vide"><i data-lucide="users-round"></i><p>{{ $this->candidates->isEmpty() ? 'Aucune candidate.' : 'Aucune candidate ne correspond aux filtres.' }}</p></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $this->pageCandidates->onEachSide(1)->links('pagination.tresor-livewire', ['libelle' => 'candidates']) }}
    </div>
    @endif

    @if ($onglet === 'liste')
    <div id="liste-electorale">
        <div class="barre-filtres">
            <x-filtre libelle="Recherche" pour="filtre-recherche" large>
                <div class="champ-icone"><i data-lucide="search"></i><input id="filtre-recherche" type="search" class="champ" wire:model.live.debounce.400ms="recherche" placeholder="Matricule ou nom"></div>
            </x-filtre>
            <x-fin-filtres :total="$this->inscriptions->total()" libelle="inscrit(s)" :actifs="trim($recherche) !== ''" :reinitialiser="route('scrutins.show', [$scrutin, 'onglet' => 'liste'])" />
        </div>
        <div class="tableau-defilant" wire:loading.class="est-en-chargement">
            <table class="tableau tableau--fiches">
                <thead><tr><th>Matricule</th><th>Agent</th><th>Sexe</th><th>Structure à la date de gel</th></tr></thead>
                <tbody>
                    @forelse ($this->inscriptions as $inscription)
                        <tr wire:key="inscription-{{ $inscription->id }}">
                            <td class="reference">{{ $inscription->agent->matricule }}</td>
                            <td>{{ $inscription->agent->nomComplet() }}</td>
                            <td>{{ $inscription->agent->sexe->value }}</td>
                            <td>{{ $inscription->structure->code }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4"><div class="etat-vide"><p>Aucun inscrit.</p></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $this->inscriptions->onEachSide(1)->links('pagination.tresor-livewire', ['libelle' => 'inscrits']) }}
    </div>
    @endif

    @if ($onglet === 'zone')
        <div class="tableau-defilant">
            <table class="tableau tableau--fiches">
                <thead><tr><th>Département</th><th>Type de structure</th><th>Structures</th></tr></thead>
                <tbody>
                    @foreach ($circonscription as $groupe)
                        @foreach ($groupe['types'] as $type)
                            <tr>
                                <td>{{ $groupe['departement'] ?? 'Structures centrales' }}</td>
                                <td>{{ $type['libelle'] }}@if (count($type['codes']) > 1) <span class="aide">({{ count($type['codes']) }})</span>@endif</td>
                                <td>{{ implode(' · ', $type['codes']) }}</td>
                            </tr>
                        @endforeach
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <div hidden>
        @if ($nominationOuverte)
            @foreach ($this->pageCandidates->getCollection()->where('origine', OrigineCandidate::Nomination) as $candidate)
                <x-modale-confirmation :id="'modale-retirer-nomination-'.$candidate->id" :titre="'Retirer la nomination de '.$candidate->agent->nomComplet().' ?'" :action="route('nominations.destroy', $candidate)" bouton="Retirer la nomination" icone="user-minus" :danger="true">
                    Elle ne sera plus candidate à ce scrutin et reste électrice. Le retrait est tracé au journal. Pour une nominée qui renonce à concourir, désactivez plutôt sa candidature.
                </x-modale-confirmation>
            @endforeach
        @endif
        @if ($scrutin->accepteLesDesistements())
            @foreach ($this->pageCandidates->getCollection()->filter->estActive() as $candidate)
                @can('desactiver', $candidate)
                    <x-modale-confirmation :id="'modale-desactiver-'.$candidate->id" :titre="'Désactiver '.$candidate->agent->nomComplet().' ?'" :action="route('candidatures.desister', $candidate)" bouton="Désactiver la candidate" icone="user-x" :danger="true">
                        À sa demande, la candidate ne figurera plus sur le bulletin et ne pourra recevoir aucune voix. Elle reste électrice et peut être réactivée jusqu’à l’ouverture.
                        <x-slot:champs>
                            <label class="libelle" for="motif-{{ $candidate->id }}">Motif (facultatif)</label>
                            <textarea id="motif-{{ $candidate->id }}" name="motif" class="champ" maxlength="500" placeholder="Ex. : demande écrite du 18 novembre"></textarea>
                        </x-slot:champs>
                    </x-modale-confirmation>
                @endcan
            @endforeach
        @endif
    </div>
</div>
