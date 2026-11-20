@props(['etape'])
{{-- Indicateur des deux étapes de la connexion : matricule, puis code reçu par e-mail. --}}
<div class="etape-connexion" aria-label="Étape {{ $etape }} sur 2">
    <span @class(['etape-connexion__barre', 'est-faite' => $etape > 1, 'est-en-cours' => $etape === 1])></span>
    <span @class(['etape-connexion__barre', 'est-en-cours' => $etape === 2])></span>
    <span class="etape-connexion__texte">Étape {{ $etape }} sur 2</span>
</div>
