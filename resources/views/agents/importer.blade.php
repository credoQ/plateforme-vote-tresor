<x-layouts.app titre="Importer des agents" :ariane="['Administration', 'Agents', 'Importer']" actif="agents">
    <div class="titre-page">
        <div>
            <h1>Importer des agents</h1>
        </div>
        <div class="titre-page__actions">
            <a href="{{ route('agents.index') }}" class="btn btn-tertiaire" wire:navigate><i data-lucide="arrow-left"></i>Retour aux agents</a>
        </div>
    </div>

    <livewire:agents.import-agents />
</x-layouts.app>
