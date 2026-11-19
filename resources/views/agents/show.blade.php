<x-layouts.app :titre="$agent->nomComplet()" :ariane="['Administration', 'Agents', $agent->matricule]" actif="agents">
    <div class="titre-page">
        <div>
            <div class="surtitre">Matricule {{ $agent->matricule }}</div>
            <h1>{{ $agent->nomComplet() }}</h1>
            <p>
                {{ $agent->sexe->libelle() }}
                @if ($affectation) · en service à {{ $affectation->structure->libelle }} depuis le {{ $affectation->date_debut->format('d/m/Y') }} @else · sans affectation en cours @endif
            </p>
        </div>
        <div class="titre-page__actions">
            @if ($peutModifier)
                <a href="{{ route('agents.edit', $agent) }}" class="btn btn-secondaire"><i data-lucide="pencil"></i>Modifier la fiche</a>
            @endif
            <a href="{{ route('agents.index') }}" class="btn btn-tertiaire"><i data-lucide="arrow-left"></i>Retour à la liste</a>
        </div>
    </div>

    @if ($ineligibilite)
        <div class="alerte alerte--attente espace-bloc"><i data-lucide="crown"></i><div><strong>Inéligible</strong> · {{ ucfirst($ineligibilite) }}.</div></div>
    @endif

    <div class="grille-deux espace-bloc">
        <section class="carte">
            <div class="carte__entete"><h2>Fiche</h2></div>
            <div class="tableau-defilant">
                <table class="tableau">
                    <tbody>
                        <tr><td>NPI</td><td>{{ $agent->npi ?? 'Non renseigné' }}</td></tr>
                        <tr><td>Date de naissance</td><td>{{ $agent->date_naissance?->format('d/m/Y') ?? 'Non renseignée' }}</td></tr>
                        <tr><td>Prise de service</td><td>{{ $agent->date_prise_service?->format('d/m/Y') }}</td></tr>
                        <tr><td>Fonction</td><td>{{ $agent->fonction }}</td></tr>
                        <tr><td>Statut</td><td>{{ $agent->statut }}</td></tr>
                        <tr><td>Contact</td><td>{{ collect([$agent->telephone, $agent->email])->filter()->implode(' · ') }}</td></tr>
                        <tr><td>Compte</td><td>{{ $agent->compte ? $agent->compte->login.($agent->compte->actif ? '' : ' (désactivé)') : 'Aucun compte' }}</td></tr>
                    </tbody>
                </table>
            </div>
        </section>

        @if ($peutModifier)
            <section class="carte">
                <div class="carte__entete"><h2>Mutation</h2></div>
                <div class="carte__corps">
                    <form method="POST" action="{{ route('agents.affecter', $agent) }}" class="formulaire">
                        @csrf
                        <div class="formulaire__groupe">
                            <label class="libelle" for="structure_id">Nouvelle structure <span class="requis">*</span></label>
                            <x-select-recherche>
                                <select id="structure_id" name="structure_id" class="champ" required>
                                    <option value="">Choisir</option>
                                    @foreach ($structures as $structure)
                                        <option value="{{ $structure->id }}" @selected((int) old('structure_id') === $structure->id)>{{ $structure->code }} · {{ $structure->libelle }}</option>
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
                        <p class="aide">L’affectation en cours sera close la veille.</p>
                        <div class="formulaire__actions"><button type="submit" class="btn btn-primaire"><i data-lucide="arrow-right-left"></i>Enregistrer la mutation</button></div>
                    </form>
                    @if ($affectation)
                        <div class="formulaire__actions">
                            <button type="button" class="btn btn-danger-contour" data-ouvrir="modale-terminer"><i data-lucide="log-out"></i>Mettre fin à l’affectation</button>
                        </div>
                    @endif
                </div>
            </section>
        @endif
    </div>

    <section class="carte">
        <div class="carte__entete"><h2>Historique des affectations</h2></div>
        <div class="tableau-defilant">
            <table class="tableau tableau--fiches">
                <thead><tr><th>Structure</th><th>Du</th><th>Au</th><th></th></tr></thead>
                <tbody>
                    @forelse ($agent->affectations as $ligne)
                        <tr>
                            <td>{{ $ligne->structure->code }}<div class="aide">{{ $ligne->structure->libelle }}</div></td>
                            <td>{{ $ligne->date_debut->format('d/m/Y') }}</td>
                            <td>{{ $ligne->date_fin?->format('d/m/Y') ?? '' }}</td>
                            <td>@if ($ligne->date_fin === null)<span class="badge badge--succes">En cours</span>@endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="4"><div class="etat-vide"><p>Aucune affectation enregistrée.</p></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
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
