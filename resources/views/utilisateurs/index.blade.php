<x-layouts.app titre="Utilisateurs" :ariane="['Administration', 'Utilisateurs']" actif="utilisateurs">
    <div class="titre-page">
        <div>
            <h1>Utilisateurs</h1>
        </div>
        <div class="titre-page__actions">
            <a href="{{ route('utilisateurs.create') }}" class="btn btn-primaire"><i data-lucide="user-plus"></i>Créer un compte</a>
        </div>
    </div>

    <div class="carte">
        <livewire:acces.liste-utilisateurs />
    </div>
</x-layouts.app>
