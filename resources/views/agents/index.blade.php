<x-layouts.app titre="Agents" :ariane="['Administration', 'Agents']" actif="agents">
    <div class="titre-page">
        <div>
            <h1>{{ $restreint ? 'Agents de ma structure' : 'Agents' }}</h1>
        </div>
        @can('create', \App\Models\Agent::class)
            <div class="titre-page__actions">
                <a href="{{ route('agents.create') }}" class="btn btn-primaire" wire:navigate><i data-lucide="user-plus"></i>Saisir un agent</a>
            </div>
        @endcan
    </div>

    <div class="carte">
        <livewire:agents.liste-agents />
    </div>
</x-layouts.app>
