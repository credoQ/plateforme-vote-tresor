<x-layouts.app centre :titre="$structure->exists ? 'Structure '.$structure->code : 'Créer une structure'" :ariane="['Administration', 'Structures', $structure->exists ? $structure->code : 'Créer']" actif="structures">
    <livewire:referentiel.formulaire-structure :structure="$structure" :parente="$parente" />
</x-layouts.app>
