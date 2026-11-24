<x-layouts.app centre :titre="! $utilisateur->exists ? 'Créer un compte' : 'Compte '.$utilisateur->login" :ariane="['Administration', 'Utilisateurs', ! $utilisateur->exists ? 'Créer' : $utilisateur->login]" actif="utilisateurs">
    <div class="titre-page">
        <div>
            <h1>{{ ! $utilisateur->exists ? 'Créer un compte' : $utilisateur->name }}</h1>
        </div>
    </div>

    <livewire:acces.formulaire-utilisateur :utilisateur="$utilisateur" />
</x-layouts.app>
