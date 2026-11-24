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

    <section class="carte espace-bloc">
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

        </div>
        @if ($peutModifier)
            <div class="carte__pied">
                @if ($affectation)
                    <button type="button" class="btn btn-danger-contour" x-on:click="$dispatch('ouvrir-modale', 'terminer')"><i data-lucide="log-out"></i>Mettre fin à l’affectation</button>
                @endif
                <button type="button" class="btn btn-primaire" x-on:click="$dispatch('ouvrir-modale', 'mutation')"><i data-lucide="arrow-right-left"></i>{{ $affectation ? 'Muter l’agent' : 'Affecter l’agent' }}</button>
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
        <livewire:agents.historique-affectations :agent="$agent" />
    </section>

    <x-slot:modales>
        @if ($peutModifier)
            {{-- Mutation (ou première affectation) : rouverte d'elle-même si la saisie est refusée. --}}
            <div class="voile" x-data="{ ouvert: @js($errors->hasAny(['structure_id', 'date_debut'])) }" x-show="ouvert" x-cloak
                 x-on:ouvrir-modale.window="ouvert = $event.detail === 'mutation'" x-on:keydown.escape.window="ouvert = false" x-on:click.self="ouvert = false">
                <form method="POST" action="{{ route('agents.affecter', $agent) }}" class="modale" role="dialog" aria-modal="true" aria-labelledby="titre-mutation">
                    @csrf
                    <div class="modale__entete">
                        <span class="pastille-icone"><i data-lucide="arrow-right-left"></i></span>
                        <div>
                            <h2 id="titre-mutation">{{ $affectation ? 'Muter' : 'Affecter' }} {{ $agent->nomComplet() }}</h2>
                            @if ($affectation)<p>L’affectation à {{ $affectation->structure->code }} sera close la veille de la nouvelle affectation.</p>@endif
                        </div>
                        <button type="button" class="icone-bouton modale__fermer" aria-label="Fermer" x-on:click="ouvert = false"><i data-lucide="x"></i></button>
                    </div>
                    <div class="modale__corps formulaire">
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
                            <input id="date_debut" name="date_debut" type="date" class="champ" value="{{ old('date_debut', now()->toDateString()) }}" required>
                            @error('date_debut')<p class="erreur-champ">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    <div class="modale__pied">
                        <button type="button" class="btn btn-secondaire" x-on:click="ouvert = false">Annuler</button>
                        <button type="submit" class="btn btn-primaire"><i data-lucide="arrow-right-left"></i>{{ $affectation ? 'Enregistrer la mutation' : 'Enregistrer l’affectation' }}</button>
                    </div>
                </form>
            </div>
        @endif

        @if ($peutModifier && $affectation)
            <div class="voile" x-data="{ ouvert: @js($errors->has('date_fin')) }" x-show="ouvert" x-cloak
                 x-on:ouvrir-modale.window="ouvert = $event.detail === 'terminer'" x-on:keydown.escape.window="ouvert = false" x-on:click.self="ouvert = false">
                <form method="POST" action="{{ route('agents.terminer', $agent) }}" class="modale" role="dialog" aria-modal="true" aria-labelledby="titre-terminer">
                    @csrf
                    <div class="modale__entete">
                        <span class="pastille-icone pastille-icone--danger"><i data-lucide="log-out"></i></span>
                        <div>
                            <h2 id="titre-terminer">Mettre fin à l’affectation de {{ $agent->nomComplet() }}</h2>
                            <p>En cas de départ (retraite, fin de détachement), l’affectation à {{ $affectation->structure->code }} est close : l’agent ne sera plus en service après cette date.</p>
                        </div>
                        <button type="button" class="icone-bouton modale__fermer" aria-label="Fermer" x-on:click="ouvert = false"><i data-lucide="x"></i></button>
                    </div>
                    <div class="modale__corps formulaire">
                        <div class="formulaire__groupe">
                            <label class="libelle" for="date_fin">Dernier jour de service <span class="requis">*</span></label>
                            <input id="date_fin" name="date_fin" type="date" class="champ" value="{{ old('date_fin', now()->toDateString()) }}" required>
                            @error('date_fin')<p class="erreur-champ">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    <div class="modale__pied">
                        <button type="button" class="btn btn-secondaire" x-on:click="ouvert = false">Annuler</button>
                        <button type="submit" class="btn btn-danger"><i data-lucide="log-out"></i>Mettre fin à l’affectation</button>
                    </div>
                </form>
            </div>
        @endif
    </x-slot:modales>
</x-layouts.app>
