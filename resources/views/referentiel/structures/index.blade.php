<x-layouts.app titre="Structures" :ariane="['Administration', 'Structures']" actif="structures">
    <div class="titre-page">
        <div>
            <h1>Structures</h1>
        </div>
        @can('create', \App\Models\Structure::class)
            <div class="titre-page__actions">
                <a wire:navigate href="{{ route('structures.create') }}" class="btn btn-primaire"><i data-lucide="plus"></i>Créer une structure</a>
            </div>
        @endcan
    </div>

    <livewire:referentiel.onglets-referentiel />
</x-layouts.app>
