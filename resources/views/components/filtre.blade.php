@props(['libelle', 'pour', 'large' => false])
{{-- Un filtre de la barre (x-barre-filtres) : libellé au-dessus du champ. --}}
<div {{ $attributes->class(['filtre', 'filtre--large' => $large]) }}>
    <label class="filtre__libelle" for="{{ $pour }}">{{ $libelle }}</label>
    {{ $slot }}
</div>
