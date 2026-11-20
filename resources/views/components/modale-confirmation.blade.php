@props(['id', 'titre', 'action', 'bouton', 'icone' => 'check', 'danger' => false])
{{-- Confirmation d'une action sensible (SweetAlert, resources/js/alertes.js) : le bouton data-ouvrir="id" ouvre la
     fenêtre, qui nomme l'objet et dit ce qui va se passer (règles des vues). Les champs éventuels (motif…) passent
     dans la fenêtre le temps de la saisie, puis le formulaire masqué est envoyé. --}}
<form method="POST" action="{{ $action }}" id="{{ $id }}" class="confirmation" hidden
      data-titre="{{ $titre }}" data-bouton="{{ $bouton }}" @if ($danger) data-danger @endif>
    @csrf
    <p class="confirmation__texte">{{ $slot }}</p>
    <div class="confirmation__champs">{{ $champs ?? '' }}</div>
</form>
