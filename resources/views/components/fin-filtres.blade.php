@props(['total', 'libelle', 'actifs' => false, 'reinitialiser'])
{{-- Fin de la barre de filtres d'un composant Livewire : indicateur de mise à jour, lien « Réinitialiser » quand un
     filtre est appliqué (nouvelle visite de la page sans paramètre), nombre de lignes trouvées. --}}
<div class="barre-filtres__fin">
    <span class="barre-filtres__chargement" wire:loading.flex aria-live="polite"><span class="roue" aria-hidden="true"></span>Mise à jour…</span>
    @if ($actifs)
        <a href="{{ $reinitialiser }}" class="lien" wire:navigate><i data-lucide="x"></i>Réinitialiser</a>
    @endif
    <span class="aide barre-filtres__total"><b class="chiffres">{{ number_format($total, 0, ',', ' ') }}</b> {{ $libelle }}</span>
</div>
