@props(['phase'])
{{-- Scrutins d'une phase : complété par le module 05 (génération et accès aux scrutins). --}}
<p class="aide">{{ $phase->scrutins->count() }} scrutin(s) générés.</p>
