<x-layouts.app titre="Éditions" :ariane="['Pilotage', 'Éditions']" actif="editions">
    <div class="titre-page">
        <div>
            <h1>Éditions</h1>
        </div>
        <div class="titre-page__actions">
            <a href="{{ route('editions.create') }}" class="btn btn-primaire"><i data-lucide="plus"></i>Créer une édition</a>
        </div>
    </div>

    <div class="carte">
        <livewire:editions.liste-editions />
    </div>
</x-layouts.app>
