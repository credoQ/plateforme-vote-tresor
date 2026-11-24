@props(['libelle', 'pour', 'large' => false])
{{-- Un filtre d'une barre de filtres (.barre-filtres) : libellé au-dessus du champ. --}}
<div {{ $attributes->class(['filtre', 'filtre--large' => $large]) }}>
    <label class="filtre__libelle" for="{{ $pour }}">{{ $libelle }}</label>
    {{ $slot }}
</div>
