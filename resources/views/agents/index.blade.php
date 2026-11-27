<x-layouts.app titre="Agents" :ariane="['Administration', 'Agents']" actif="agents">
    <div class="titre-page">
        <div>
            <h1>{{ $restreint ? 'Agents de ma structure' : 'Agents' }}</h1>
        </div>
        @can('create', \App\Models\Agent::class)
            <div class="titre-page__actions">
                <button type="button" class="btn btn-secondaire" x-data x-on:click="$dispatch('ouvrir-import-agents')"><i data-lucide="upload"></i>Importer un fichier</button>
                <a href="{{ route('agents.create') }}" class="btn btn-primaire" wire:navigate><i data-lucide="user-plus"></i>Saisir un agent</a>
            </div>
        @endcan
    </div>

    <div class="carte">
        <livewire:agents.liste-agents />
    </div>

    @can('create', \App\Models\Agent::class)
        <livewire:agents.import-agents />
    @endcan
</x-layouts.app>
