@use('App\Enums\Scrutins\OrigineCandidate')
<x-layouts.app :titre="$scrutin->libelle" :ariane="['Pilotage', 'Édition '.$scrutin->phase->edition->annee, $scrutin->code]" :actif="$pilote ? 'editions' : 'tableau-de-bord'">
    <div class="titre-page">
        <div>
            <div class="surtitre">{{ $scrutin->phase->libelle }} · {{ $scrutin->poleElectoral?->code->libelle() ?? 'Toute la DGTCP' }}</div>
            <h1>{{ $scrutin->libelle }}</h1>
            <p>
                {{ $scrutin->statut->libelle() }}
                @if ($scrutin->date_heure_ouverture)
                    · du {{ $scrutin->date_heure_ouverture->timezone($fuseau)->format('d/m/Y H:i') }} au {{ $scrutin->date_heure_fermeture->timezone($fuseau)->format('d/m/Y H:i') }}
                @endif
                @if ($restreint) · données limitées à votre structure @endif
            </p>
        </div>
        <div class="titre-page__actions">
            <x-liens-export route="exports.liste-electorale" :parametres="[$scrutin]" libelle="Liste électorale :" />
            @if ($pilote)
                <a wire:navigate href="{{ route('editions.show', [$scrutin->phase->edition, 'phase' => $scrutin->phase->numero]) }}" class="btn btn-tertiaire"><i data-lucide="arrow-left"></i>Retour à l’édition</a>
            @endif
        </div>
    </div>

    <div class="grille-tuiles espace-bloc">
        <div class="tuile">
            <div class="tuile__haut"><span class="surtitre">Inscrits</span><span class="tuile__icone"><i data-lucide="users"></i></span></div>
            <div class="tuile__valeur">{{ number_format($participation['inscrits'], 0, ',', ' ') }}</div>
            <div class="tuile__detail">{{ $scrutin->listesSontModifiables() ? 'Listes en préparation' : 'Listes arrêtées' }}</div>
        </div>
        <div class="tuile">
            <div class="tuile__haut"><span class="surtitre">Candidates actives</span><span class="tuile__icone"><i data-lucide="users-round"></i></span></div>
            <div class="tuile__valeur">{{ $actives->count() }}</div>
            <div class="tuile__detail">{{ $candidates->count() - $actives->count() }} désistement(s)</div>
        </div>
        <div class="tuile">
            <div class="tuile__haut"><span class="surtitre">Participation</span><span class="tuile__icone"><i data-lucide="percent"></i></span></div>
            <div class="tuile__valeur">{{ number_format($participation['taux'], 1, ',', ' ') }} %</div>
            <div class="tuile__detail">{{ $participation['votants'] }} votant(s)</div>
        </div>
    </div>

    @if ($pilote && $scrutin->accepteLesDesistements())
        <section class="carte espace-bloc">
            <div class="carte__entete">
                <h2>Préparation</h2>
                <span class="etiquette"><i data-lucide="calendar-range"></i>{{ $scrutin->phase->libelle }} : @if ($scrutin->phase->date_debut)du {{ $scrutin->phase->date_debut->format('d/m/Y') }} au {{ $scrutin->phase->date_fin->format('d/m/Y') }}@else à planifier @endif</span>
            </div>
            <div class="carte__corps">
                <form method="POST" action="{{ route('scrutins.planifier', $scrutin) }}" class="formulaire-ligne">
                    @csrf
                    <div class="formulaire__groupe">
                        <label class="libelle" for="ouverture">Ouverture (heure du Bénin)</label>
                        <input id="ouverture" name="ouverture" type="datetime-local" class="champ" value="{{ old('ouverture', $scrutin->date_heure_ouverture?->timezone($fuseau)->format('Y-m-d\TH:i')) }}" required>
                        @error('ouverture')<p class="erreur-champ">{{ $message }}</p>@enderror
                    </div>
                    <div class="formulaire__groupe">
                        <label class="libelle" for="fermeture">Fermeture (heure du Bénin)</label>
                        <input id="fermeture" name="fermeture" type="datetime-local" class="champ" value="{{ old('fermeture', $scrutin->date_heure_fermeture?->timezone($fuseau)->format('Y-m-d\TH:i')) }}" required>
                        @error('fermeture')<p class="erreur-champ">{{ $message }}</p>@enderror
                    </div>
                    <button type="submit" class="btn btn-secondaire"><i data-lucide="calendar-clock"></i>Planifier le scrutin</button>
                </form>
                @if ($scrutin->listesSontModifiables())
                    <div class="formulaire__actions">
                        <form method="POST" action="{{ route('scrutins.actualiser', $scrutin) }}">
                            @csrf
                            <button type="submit" class="btn btn-secondaire"><i data-lucide="refresh-cw"></i>Actualiser les listes</button>
                        </form>
                        <button type="button" class="btn btn-primaire" data-ouvrir="modale-arreter"><i data-lucide="lock"></i>Arrêter les listes</button>
                    </div>
                @else
                    <div class="formulaire__actions">
                        <button type="button" class="btn btn-secondaire" data-ouvrir="modale-rouvrir"><i data-lucide="lock-open"></i>Rouvrir les listes</button>
                    </div>
                    @error('motif')<p class="erreur-champ">{{ $message }}</p>@enderror
                @endif
            </div>
        </section>
    @endif

    @include('resultats._scrutin')

    <section class="carte espace-bloc" id="candidates">
        <div class="carte__entete"><h2>Candidates</h2><span class="etiquette">{{ $scrutin->college->origine_candidates->libelleCollege() }} · {{ $scrutin->nbElues() }} élue(s)</span></div>
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
        <x-barre-filtres :total="$pageCandidates->total()" libelle="candidate(s)" :action="route('scrutins.show', $scrutin).'#candidates'" :conserver="['recherche' => $recherche]">
            <x-filtre libelle="Recherche" pour="filtre-candidate" large>
                <div class="champ-icone"><i data-lucide="search"></i><input id="filtre-candidate" type="search" name="candidate" class="champ" value="{{ $filtresCandidates['candidate'] }}" placeholder="Nom ou matricule"></div>
            </x-filtre>
            @if ($structures->count() > 1)
                <x-filtre libelle="Structure" pour="filtre-structure-candidate">
                    <x-select-recherche compact soumettre>
                        <select id="filtre-structure-candidate" name="structure_candidate" class="champ">
                            <option value="">Toutes les structures</option>
                            @foreach ($structures as $structure)
                                <option value="{{ $structure->id }}" data-detail="{{ $structure->libelle }}" @selected($filtresCandidates['structure_candidate'] === $structure->id)>{{ $structure->code }}</option>
                            @endforeach
                        </select>
                    </x-select-recherche>
                </x-filtre>
            @endif
            <x-filtre libelle="Statut" pour="filtre-statut-candidate">
                <x-select-recherche compact soumettre>
                    <select id="filtre-statut-candidate" name="statut_candidate" class="champ">
                        <option value="">Tous les statuts</option>
                        @foreach (\App\Enums\Scrutins\StatutCandidate::cases() as $cas)
                            <option value="{{ $cas->value }}" @selected($filtresCandidates['statut_candidate'] === $cas)>{{ $cas->libelle() }}</option>
                        @endforeach
                    </select>
                </x-select-recherche>
            </x-filtre>
        </x-barre-filtres>
        <div class="tableau-defilant">
            <table class="tableau tableau--fiches">
                <thead><tr><th>Candidate</th><th>Structure</th><th>Origine</th><th>Statut</th><th class="actions">Actions</th></tr></thead>
                <tbody>
                    @forelse ($pageCandidates as $candidate)
                        <tr>
                            <td>{{ $candidate->agent->nomComplet() }}<div class="aide">{{ $candidate->agent->matricule }}</div></td>
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
                        <tr><td colspan="5"><div class="etat-vide"><i data-lucide="users-round"></i><p>{{ $candidates->isEmpty() ? 'Aucune candidate.' : 'Aucune candidate ne correspond aux filtres.' }}</p></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $pageCandidates->onEachSide(1)->links('pagination.tresor', ['libelle' => 'candidates']) }}
    </section>

    <section class="carte espace-bloc" id="liste-electorale">
        <div class="carte__entete"><h2>Liste électorale</h2></div>
        <x-barre-filtres :total="$inscriptions->total()" libelle="inscrit(s)" :action="route('scrutins.show', $scrutin).'#liste-electorale'"
                         :conserver="['candidate' => $filtresCandidates['candidate'], 'statut_candidate' => $filtresCandidates['statut_candidate']?->value, 'structure_candidate' => $filtresCandidates['structure_candidate']]">
            <x-filtre libelle="Recherche" pour="filtre-recherche" large>
                <div class="champ-icone"><i data-lucide="search"></i><input id="filtre-recherche" type="search" name="recherche" class="champ" value="{{ $recherche }}" placeholder="Matricule ou nom"></div>
            </x-filtre>
        </x-barre-filtres>
        <div class="tableau-defilant">
            <table class="tableau tableau--fiches">
                <thead><tr><th>Matricule</th><th>Agent</th><th>Sexe</th><th>Structure à la date de gel</th></tr></thead>
                <tbody>
                    @forelse ($inscriptions as $inscription)
                        <tr>
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
        {{ $inscriptions->onEachSide(1)->links('pagination.tresor', ['libelle' => 'inscrits']) }}
    </section>

    <section class="carte">
        <div class="carte__entete"><h2>Circonscription</h2></div>
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
    </section>

    <x-slot:modales>
        @if ($pilote && $scrutin->listesSontModifiables())
            <x-modale-confirmation id="modale-arreter" :titre="'Arrêter les listes de « '.$scrutin->libelle.' » ?'" :action="route('scrutins.arreter', $scrutin)" bouton="Arrêter les listes" icone="lock">
                Les listes sont actualisées une dernière fois d’après les affectations à la date de gel, puis figées. Seules les désactivations et réactivations de candidates resteront possibles jusqu’à l’ouverture.
            </x-modale-confirmation>
        @endif
        @if ($pilote && $scrutin->listesPeuventEtreRouvertes())
            <x-modale-confirmation id="modale-rouvrir" :titre="'Rouvrir les listes de « '.$scrutin->libelle.' » ?'" :action="route('scrutins.rouvrir', $scrutin)" bouton="Rouvrir les listes" icone="lock-open">
                La décision et son motif sont tracés au journal. Le scrutin ne s’ouvrira pas tant que les listes ne seront pas de nouveau arrêtées. Les candidates désactivées le restent.
                <x-slot:champs>
                    <label class="libelle" for="motif-rouvrir">Motif de la décision du comité</label>
                    <textarea id="motif-rouvrir" name="motif" class="champ" maxlength="500" required placeholder="Ex. : agente omise de l’import RH, affectée à la TD Borgou à la date de gel"></textarea>
                </x-slot:champs>
            </x-modale-confirmation>
        @endif
        @if ($nominationOuverte)
            @foreach ($pageCandidates->where('origine', OrigineCandidate::Nomination) as $candidate)
                <x-modale-confirmation :id="'modale-retirer-nomination-'.$candidate->id" :titre="'Retirer la nomination de '.$candidate->agent->nomComplet().' ?'" :action="route('nominations.destroy', $candidate)" bouton="Retirer la nomination" icone="user-minus" :danger="true">
                    Elle ne sera plus candidate à ce scrutin et reste électrice. Le retrait est tracé au journal. Pour une nominée qui renonce à concourir, désactivez plutôt sa candidature.
                </x-modale-confirmation>
            @endforeach
        @endif
        @if ($scrutin->accepteLesDesistements())
            @foreach ($pageCandidates->getCollection()->filter->estActive() as $candidate)
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
    </x-slot:modales>
</x-layouts.app>
