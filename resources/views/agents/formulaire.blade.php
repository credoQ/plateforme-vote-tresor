<x-layouts.app centre :titre="! $agent->exists ? 'Saisir un agent' : 'Agent '.$agent->matricule" :ariane="['Administration', 'Agents', ! $agent->exists ? 'Saisir' : $agent->matricule]" actif="agents">
    <livewire:agents.formulaire-agent :agent="$agent" />
</x-layouts.app>
