<x-dynamic-component :component="$gabarit" titre="Résultats" :ariane="['Mon espace', 'Résultats']" actif="palmares">
    <livewire:resultats.palmares :edition-id="$editionId" />
</x-dynamic-component>
