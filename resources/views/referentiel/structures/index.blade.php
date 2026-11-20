<x-layouts.app titre="Structures" :ariane="['Administration', 'Structures']" actif="structures">
    <div class="titre-page">
        <div>
            <h1>Structures</h1>
        </div>
        @can('create', \App\Models\Structure::class)
            <div class="titre-page__actions">
                <a href="{{ route('structures.create') }}" class="btn btn-primaire"><i data-lucide="plus"></i>Créer une structure</a>
            </div>
        @endcan
    </div>

    <div class="carte">
        <div class="onglets">
            <a href="{{ route('structures.index') }}" class="onglet est-actif">Structures</a>
            <a href="{{ route('structures.organisation') }}" class="onglet">Types et départements</a>
        </div>
        <livewire:referentiel.liste-structures />
    </div>
</x-layouts.app>
