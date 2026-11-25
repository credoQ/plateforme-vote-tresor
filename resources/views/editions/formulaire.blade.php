<x-layouts.app centre :titre="! $edition->exists ? 'Créer une édition' : 'Édition '.$edition->annee" :ariane="['Pilotage', 'Éditions', ! $edition->exists ? 'Créer' : (string) $edition->annee]" actif="editions">
    <livewire:editions.formulaire-edition :edition="$edition" />
</x-layouts.app>
