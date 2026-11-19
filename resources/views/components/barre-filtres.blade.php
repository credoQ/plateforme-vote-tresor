@props(['total' => null, 'libelle' => 'élément(s)', 'action' => null])
{{-- Barre de filtres au-dessus d'un tableau (au plus quatre filtres) : chaque <x-filtre> porte son libellé,
     une liste envoie le formulaire dès qu'on la change, la recherche à la touche Entrée ou au bouton. --}}
<form method="GET" @if ($action) action="{{ $action }}" @endif {{ $attributes->class('barre-filtres') }}>
    {{ $slot }}
    <div class="barre-filtres__fin">
        <button type="submit" class="btn btn-secondaire"><i data-lucide="filter"></i>Filtrer</button>
        @if ($filtresActifs)
            <a href="{{ $adresseSansFiltre }}" class="lien"><i data-lucide="x"></i>Réinitialiser</a>
        @endif
        @if ($total !== null)
            <span class="aide barre-filtres__total"><b class="chiffres">{{ number_format($total, 0, ',', ' ') }}</b> {{ $libelle }}</span>
        @endif
    </div>
</form>
