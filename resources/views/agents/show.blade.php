<x-layouts.app :titre="$agent->nomComplet()" :ariane="['Administration', 'Agents', $agent->matricule]" actif="agents">
    <div class="titre-page">
        <div class="titre-page__identite">
            <span class="avatar avatar--grand avatar--sans-anneau" aria-hidden="true">{{ $agent->initiales() }}</span>
            <div>
                <h1>{{ $agent->nomComplet() }}</h1>
                <div class="titre-page__surtitre">
                    <span class="etiquette"><i data-lucide="id-card"></i>{{ $agent->matricule }}</span>
                    <span class="etiquette">{{ $agent->sexe->libelle() }}</span>
                    @if ($affectation)
                        <span class="badge badge--succes">En service</span>
                    @else
                        <span class="badge badge--attente">Sans affectation</span>
                    @endif
                    @if ($ineligibilite)
                        <span class="badge badge--accent">Inéligible</span>
                    @endif
                </div>
            </div>
        </div>
        <div class="titre-page__actions">
            <a wire:navigate href="{{ route('agents.index') }}" class="btn btn-tertiaire"><i data-lucide="arrow-left"></i>Retour à la liste</a>
            @if ($peutModifier)
                <a wire:navigate href="{{ route('agents.edit', $agent) }}" class="btn btn-secondaire"><i data-lucide="pencil"></i>Modifier la fiche</a>
            @endif
        </div>
    </div>

    @if ($ineligibilite)
        <div class="alerte alerte--attente espace-bloc"><i data-lucide="crown"></i><div>{{ ucfirst($ineligibilite) }}.</div></div>
    @endif

    <section class="carte espace-bloc" x-data="{ mutation: @js($errors->hasAny(['structure_id', 'date_debut'])) }">
        <div class="carte__entete"><h2>Affectation actuelle</h2></div>
        <div class="carte__corps">
            @if ($affectation)
                <div class="cellule-personne">
                    <span class="pastille-icone"><i data-lucide="building-2"></i></span>
                    <div>
                        <strong>
                            @can('view', $affectation->structure)
                                <a wire:navigate href="{{ route('structures.show', $affectation->structure) }}" class="lien">{{ $affectation->structure->code }}</a>
                            @else
                                {{ $affectation->structure->code }}
                            @endcan
                            · {{ $affectation->structure->libelle }}
                        </strong>
                        <small>
                            Depuis le {{ $affectation->date_debut->format('d/m/Y') }} · {{ $anciennete }}
                            @if ($affectation->structure->departement) · {{ $affectation->structure->departement->libelle }} @endif
                        </small>
                    </div>
                </div>
            @else
                <div class="cellule-personne">
                    <span class="pastille-icone pastille-icone--danger"><i data-lucide="building-2"></i></span>
                    <div><strong>Aucune affectation en cours</strong></div>
                </div>
            @endif

            @if ($peutModifier)
                <form method="POST" action="{{ route('agents.affecter', $agent) }}" class="formulaire formulaire--deux-colonnes affectation-mutation" x-show="mutation" x-cloak>
                    @csrf
                    <div class="formulaire__groupe">
                        <label class="libelle" for="structure_id">Nouvelle structure <span class="requis">*</span></label>
                        <x-select-recherche>
                            <select id="structure_id" name="structure_id" class="champ" required>
                                <option value="">Choisir</option>
                                @foreach ($structures as $structure)
                                    <option value="{{ $structure->id }}" data-detail="{{ $structure->libelle }}" @selected((int) old('structure_id') === $structure->id)>{{ $structure->code }}</option>
                                @endforeach
                            </select>
                        </x-select-recherche>
                        @error('structure_id')<p class="erreur-champ">{{ $message }}</p>@enderror
                    </div>
                    <div class="formulaire__groupe">
                        <label class="libelle" for="date_debut">À partir du <span class="requis">*</span></label>
                        <input id="date_debut" name="date_debut" type="date" class="champ champ--moyen" value="{{ old('date_debut', now()->toDateString()) }}" required>
                        @if ($affectation)<p class="aide">L’affectation à {{ $affectation->structure->code }} sera close la veille.</p>@endif
                        @error('date_debut')<p class="erreur-champ">{{ $message }}</p>@enderror
                    </div>
                    <div class="formulaire__actions formulaire__groupe--pleine">
                        <button type="button" class="btn btn-tertiaire" x-on:click="mutation = false">Annuler</button>
                        <button type="submit" class="btn btn-primaire"><i data-lucide="arrow-right-left"></i>Enregistrer la mutation</button>
                    </div>
                </form>
            @endif
        </div>
        @if ($peutModifier)
            <div class="carte__pied" x-show="! mutation">
                @if ($affectation)
                    <button type="button" class="btn btn-danger-contour" data-ouvrir="modale-terminer"><i data-lucide="log-out"></i>Mettre fin à l’affectation</button>
                @endif
                <button type="button" class="btn btn-primaire" x-on:click="mutation = true"><i data-lucide="arrow-right-left"></i>{{ $affectation ? 'Muter l’agent' : 'Affecter l’agent' }}</button>
            </div>
        @endif
    </section>

    <section class="carte espace-bloc">
        <div class="carte__entete"><h2>Informations</h2></div>
        <div class="carte__corps">
            <dl class="recapitulatif__liste recapitulatif__liste--colonnes">
                <div><dt>NPI</dt><dd @class(['est-vide' => ! $agent->npi])>{{ $agent->npi ?? 'Non renseigné' }}</dd></div>
                <div><dt>Date de naissance</dt><dd @class(['est-vide' => ! $agent->date_naissance])>{{ $agent->date_naissance?->format('d/m/Y') ?? 'Non renseignée' }}</dd></div>
                <div><dt>Fonction</dt><dd @class(['est-vide' => ! $agent->fonction])>{{ $agent->fonction ?: 'Non renseignée' }}</dd></div>
                <div><dt>Statut</dt><dd @class(['est-vide' => ! $agent->statut])>{{ $agent->statut ?: 'Non renseigné' }}</dd></div>
                <div><dt>Prise de service</dt><dd @class(['est-vide' => ! $agent->date_prise_service])>{{ $agent->date_prise_service?->format('d/m/Y') ?? 'Non renseignée' }}</dd></div>
                <div><dt>Téléphone</dt><dd @class(['est-vide' => ! $agent->telephone])>{{ $agent->telephone ?: 'Non renseigné' }}</dd></div>
                <div><dt>E-mail</dt><dd @class(['est-vide' => ! $agent->email])>@if ($agent->email)<a href="mailto:{{ $agent->email }}" class="lien">{{ $agent->email }}</a>@else Non renseigné @endif</dd></div>
                <div>
                    <dt>Compte</dt>
                    <dd @class(['est-vide' => ! $agent->compte])>
                        @if ($agent->compte)
                            {{ $agent->compte->login }}{{ $roles ? ' · '.$roles : '' }}
                            @unless ($agent->compte->actif) <span class="badge badge--erreur">Désactivé</span> @endunless
                        @else
                            Aucun compte
                        @endif
                    </dd>
                </div>
            </dl>
        </div>
    </section>

    <section class="carte">
        <div class="carte__entete"><h2>Historique des affectations</h2><span class="etiquette">{{ $affectations->total() }}</span></div>
        @if ($avecFiltres)
            <x-barre-filtres :total="$affectations->total()" libelle="affectation(s)">
                <x-filtre libelle="Structure" pour="filtre-recherche" large>
                    <div class="champ-icone"><i data-lucide="search"></i><input id="filtre-recherche" type="search" name="recherche" class="champ" value="{{ $recherche }}" placeholder="Code ou libellé"></div>
                </x-filtre>
                <x-filtre libelle="Période" pour="filtre-periode">
                    <x-select-recherche compact soumettre>
                        <select id="filtre-periode" name="periode" class="champ">
                            <option value="">Toutes</option>
                            <option value="en-cours" @selected($periode === 'en-cours')>En cours</option>
                            <option value="terminees" @selected($periode === 'terminees')>Terminées</option>
                        </select>
                    </x-select-recherche>
                </x-filtre>
            </x-barre-filtres>
        @endif
        <div class="tableau-defilant">
            <table class="tableau tableau--fiches">
                <thead><tr><th>Structure</th><th>Du</th><th>Au</th><th>Durée</th><th>Statut</th></tr></thead>
                <tbody>
                    @forelse ($affectations as $ligne)
                        <tr>
                            <td>{{ $ligne->structure->code }}<div class="aide">{{ $ligne->structure->libelle }}</div></td>
                            <td>{{ $ligne->date_debut->format('d/m/Y') }}</td>
                            <td>{{ $ligne->date_fin?->format('d/m/Y') ?? '-' }}</td>
                            <td>{{ $ligne->duree }}</td>
                            <td>
                                @if ($ligne->date_fin === null)
                                    <span class="badge badge--succes">En cours</span>
                                @else
                                    <span class="badge badge--info">Terminée</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><div class="etat-vide"><p>{{ $recherche !== '' || $periode !== '' ? 'Aucune affectation ne correspond aux filtres.' : 'Aucune affectation enregistrée.' }}</p></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $affectations->onEachSide(1)->links('pagination.tresor', ['libelle' => 'affectations']) }}
    </section>

    <x-slot:modales>
        @if ($peutModifier && $affectation)
            <x-modale-confirmation id="modale-terminer" :titre="'Mettre fin à l’affectation de '.$agent->nomComplet().' ?'" :action="route('agents.terminer', $agent)" bouton="Mettre fin à l’affectation" icone="log-out" :danger="true">
                En cas de départ (retraite, fin de détachement), l’affectation à {{ $affectation->structure->code }} est close : l’agent ne sera plus en service après cette date.
                <x-slot:champs>
                    <label class="libelle" for="date_fin">Dernier jour de service</label>
                    <input id="date_fin" name="date_fin" type="date" class="champ" value="{{ now()->toDateString() }}" required>
                </x-slot:champs>
            </x-modale-confirmation>
        @endif
    </x-slot:modales>
</x-layouts.app>
