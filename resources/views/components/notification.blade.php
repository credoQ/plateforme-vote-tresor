@props(['message', 'duree' => 6000])
{{-- Message de succès flottant, qui se ferme seul après $duree millisecondes ou par sa croix. --}}
<div class="notification" role="status"
     x-data="{ visible: true }" x-init="setTimeout(() => visible = false, {{ (int) $duree }})"
     x-show="visible" x-transition.opacity.duration.200ms>
    <span class="pastille-icone pastille-icone--succes"><i data-lucide="circle-check"></i></span>
    <p>{{ $message }}</p>
    <button type="button" class="icone-bouton" x-on:click="visible = false" aria-label="Fermer le message"><i data-lucide="x"></i></button>
</div>
