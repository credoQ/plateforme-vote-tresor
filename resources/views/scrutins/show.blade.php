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
                <a href="{{ route('editions.show', $scrutin->phase->edition_id) }}#phase-{{ $scrutin->phase->numero }}" class="btn btn-tertiaire"><i data-lucide="arrow-left"></i>Retour à l’édition</a>
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
            <div class="carte__entete"><h2>Préparation</h2></div>
            <div class="carte__corps">
                <form method="POST" action="{{ route('scrutins.planifier', $scrutin) }}" class="formulaire-ligne">
                    @csrf
                    <div class="formulaire__groupe">
                        <label class="libelle" for="ouverture">Ouverture (heure du Bénin)</label>
                        <input id="ouverture" name="ouverture" type="datetime-local" class="champ" value="{{ old('ouverture', $scrutin->date_heure_ouverture?->timezone($fuseau)->format('Y-m-d\TH:i')) }}" required>
                        @error('ouverture')<p class="erreur-champ">{{ $message }}</p>@enderror
                        @error('date_heure_ouverture')<p class="erreur-champ">{{ $message }}</p>@enderror
                    </div>
                    <div class="formulaire__groupe">
                        <label class="libelle" for="fermeture">Fermeture (heure du Bénin)</label>
                        <input id="fermeture" name="fermeture" type="datetime-local" class="champ" value="{{ old('fermeture', $scrutin->date_heure_fermeture?->timezone($fuseau)->format('Y-m-d\TH:i')) }}" required>
                        @error('fermeture')<p class="erreur-champ">{{ $message }}</p>@enderror
                        @error('date_heure_fermeture')<p class="erreur-champ">{{ $message }}</p>@enderror
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
                @endif
            </div>
        </section>
    @endif

    @include('resultats._scrutin')

    <section class="carte espace-bloc">
        <div class="carte__entete"><h2>Candidates</h2></div>
        <div class="tableau-defilant">
            <table class="tableau tableau--fiches">
                <thead><tr><th>Candidate</th><th>Structure</th><th>Origine</th><th>Statut</th><th class="actions">Actions</th></tr></thead>
                <tbody>
                    @forelse ($candidates as $candidate)
                        <tr>
                            <td>{{ $candidate->agent->nomComplet() }}<div class="aide">{{ $candidate->agent->matricule }}</div></td>
                            <td>{{ $structures[$candidate->structure_inscription_id]->code ?? '' }}</td>
                            <td>{{ $candidate->origine->libelle() }}</td>
                            <td>
                                @if ($candidate->estActive())
                                    <span class="badge badge--succes">Active</span>
                                @else
                                    <span class="badge badge--erreur">Désactivée</span>
                                    <div class="aide">le {{ $candidate->date_desistement->timezone($fuseau)->format('d/m/Y') }}{{ $candidate->desactiveePar ? ' par '.$candidate->desactiveePar->name : '' }}{{ $candidate->motif_desistement ? ' · '.$candidate->motif_desistement : '' }}</div>
                                @endif
                            </td>
                            <td class="actions">
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
                        <tr><td colspan="5"><div class="etat-vide"><i data-lucide="users-round"></i><p>Aucune candidate.</p></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="carte espace-bloc">
        <div class="carte__entete"><h2>Liste électorale</h2></div>
        <form method="GET" class="barre-filtres">
            <div class="champ-icone"><i data-lucide="search"></i><input name="recherche" class="champ" value="{{ $recherche }}" placeholder="Matricule ou nom" aria-label="Rechercher un inscrit"></div>
            <button type="submit" class="btn btn-secondaire"><i data-lucide="filter"></i>Rechercher</button>
        </form>
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
        {{ $inscriptions->links() }}
    </section>

    <section class="carte">
        <div class="carte__entete"><h2>Circonscription</h2></div>
        <div class="carte__corps"><p>{{ $scrutin->structures->map(fn ($s) => $s->code)->implode(' · ') }}</p></div>
    </section>

    <x-slot:modales>
        @if ($pilote && $scrutin->listesSontModifiables())
            <x-modale-confirmation id="modale-arreter" :titre="'Arrêter les listes de « '.$scrutin->libelle.' » ?'" :action="route('scrutins.arreter', $scrutin)" bouton="Arrêter les listes" icone="lock">
                Les listes sont actualisées une dernière fois d’après les affectations à la date de gel, puis figées. Seules les désactivations et réactivations de candidates resteront possibles jusqu’à l’ouverture.
            </x-modale-confirmation>
        @endif
        @if ($scrutin->accepteLesDesistements())
            @foreach ($actives as $candidate)
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
