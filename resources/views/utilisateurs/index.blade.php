<x-layouts.app titre="Utilisateurs" :ariane="['Administration', 'Utilisateurs']" actif="utilisateurs">
    <div class="titre-page">
        <div>
            <h1>Utilisateurs</h1>
        </div>
        <div class="titre-page__actions">
            <a href="{{ route('utilisateurs.create') }}" class="btn btn-primaire"><i data-lucide="user-plus"></i>Créer un compte</a>
        </div>
    </div>

    @error('agent')<div class="alerte alerte--erreur espace-bloc" role="alert"><i data-lucide="circle-alert"></i><div>{{ $message }}</div></div>@enderror

    <div class="carte">
        <livewire:acces.liste-utilisateurs />
    </div>

    <x-slot:modales>
        <x-modale-confirmation id="modale-lier-agent" titre="Lier le compte à un agent ?" action="#" bouton="Lier le compte" icone="link">
            Le compte est rattaché à la fiche de l’agent choisi ; il ne pourra plus être rattaché à un autre agent.
            <x-slot:champs>
                <label class="libelle" for="agent-a-lier">Agent sans compte</label>
                <input id="agent-a-lier" name="agent" class="champ" list="agents-sans-compte" required autocomplete="off" placeholder="Matricule ou nom">
                <datalist id="agents-sans-compte">
                    @foreach ($agentsSansCompte as $agent)
                        <option value="{{ $agent->matricule }} · {{ $agent->nomComplet() }}"></option>
                    @endforeach
                </datalist>
            </x-slot:champs>
        </x-modale-confirmation>
    </x-slot:modales>
</x-layouts.app>
