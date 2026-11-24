<x-layouts.app titre="Femmes modèles" :ariane="['Pilotage', 'Femmes modèles']" actif="femmes-modeles">
    <div class="titre-page">
        <div>
            <h1>Femmes modèles du Trésor</h1>
            @if ($edition)
                <p>Édition {{ $edition->annee }} : délai de rééligibilité de {{ $edition->delai_reeligibilite }} an(s).</p>
            @endif
        </div>
        <div class="titre-page__actions">
            <a href="{{ route('femmes-modeles.create') }}" class="btn btn-primaire" wire:navigate><i data-lucide="user-plus"></i>Ajouter une femme modèle</a>
        </div>
    </div>

    @error('femme_modele')<div class="alerte alerte--erreur espace-bloc" role="alert"><i data-lucide="circle-alert"></i><div>{{ $message }}</div></div>@enderror

    <section class="carte">
        <div class="carte__entete"><h2>Historique</h2></div>
        <livewire:editions.liste-femmes-modeles />
    </section>

</x-layouts.app>
