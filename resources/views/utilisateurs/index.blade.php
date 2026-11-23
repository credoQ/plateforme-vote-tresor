<x-layouts.app titre="Utilisateurs" :ariane="['Administration', 'Utilisateurs']" actif="utilisateurs">
    <div class="titre-page">
        <div>
            <h1>Utilisateurs</h1>
        </div>
        <div class="titre-page__actions">
            <a wire:navigate href="{{ route('utilisateurs.create') }}" class="btn btn-primaire"><i data-lucide="user-plus"></i>Créer un compte</a>
        </div>
    </div>

    @error('agent')<div class="alerte alerte--erreur espace-bloc" role="alert"><i data-lucide="circle-alert"></i><div>{{ $message }}</div></div>@enderror

    <div class="carte">
        <livewire:acces.liste-utilisateurs />
    </div>

    <x-slot:modales>
        {{-- Fenêtre « Lier à un agent » : ouverte par le bouton d'une ligne de la liste (événement ouvrir-liaison). --}}
        <div class="voile" x-data="{ liaison: null }" x-show="liaison" x-cloak
             x-on:ouvrir-liaison.window="liaison = $event.detail" x-on:keydown.escape.window="liaison = null" x-on:click.self="liaison = null">
            <form method="POST" x-bind:action="liaison?.action" class="modale" role="dialog" aria-modal="true" aria-labelledby="titre-liaison">
                @csrf
                <div class="modale__entete">
                    <span class="pastille-icone"><i data-lucide="link"></i></span>
                    <div>
                        <h2 id="titre-liaison" x-text="'Lier le compte ' + (liaison?.login ?? '') + ' à un agent'"></h2>
                        <p>Le compte est rattaché à la fiche de l’agent choisi ; il ne pourra plus être rattaché à un autre agent.</p>
                    </div>
                    <button type="button" class="icone-bouton modale__fermer" aria-label="Fermer" x-on:click="liaison = null"><i data-lucide="x"></i></button>
                </div>
                <div class="modale__corps formulaire">
                    <div class="formulaire__groupe">
                        <label class="libelle" for="agent-a-lier">Agent sans compte <span class="requis">*</span></label>
                        @if ($agentsSansCompte->isEmpty())
                            <p class="aide">Tous les agents ont déjà un compte.</p>
                        @else
                            <x-select-recherche placeholder="Rechercher un agent">
                                <select id="agent-a-lier" name="agent" class="champ" required>
                                    <option value="">Choisir</option>
                                    @foreach ($agentsSansCompte as $agent)
                                        <option value="{{ $agent->matricule }}" data-detail="{{ $agent->matricule }}">{{ $agent->nomComplet() }}</option>
                                    @endforeach
                                </select>
                            </x-select-recherche>
                        @endif
                    </div>
                </div>
                <div class="modale__pied">
                    <button type="button" class="btn btn-secondaire" x-on:click="liaison = null">Annuler</button>
                    <button type="submit" class="btn btn-primaire" @disabled($agentsSansCompte->isEmpty())><i data-lucide="link"></i>Lier le compte</button>
                </div>
            </form>
        </div>
    </x-slot:modales>
</x-layouts.app>
