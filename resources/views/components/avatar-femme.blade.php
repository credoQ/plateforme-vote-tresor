@props(['variante'])
{{-- Avatar dessiné d'une candidate sans photo : silhouette de femme sans traits du visage, au format de la carte du
     bulletin (4 × 4,6). La variante (coiffure, teint, cheveux, tenue) vient de Agent::avatar() : la même pour
     chaque agent. Couleurs : tokens --tr-avatar-* (composants.css, .avatar-femme). --}}
<svg {{ $attributes->class(['avatar-femme', 'avatar-femme--t'.$variante['tenue'], 'avatar-femme--p'.$variante['peau'], 'avatar-femme--h'.$variante['cheveux']]) }}
     viewBox="0 0 400 460" preserveAspectRatio="xMidYMin slice" aria-hidden="true" focusable="false">
    <rect class="avatar-femme__fond" width="400" height="460"/>
    <g transform="translate(0 -16)">
    @if ($variante['coiffure'] === 1)
        <rect class="avatar-femme__cheveux" x="118" y="96" width="164" height="250" rx="82"/>
    @elseif ($variante['coiffure'] === 4)
        <rect class="avatar-femme__cheveux" x="122" y="100" width="156" height="170" rx="72"/>
    @endif
    <path class="avatar-femme__tenue" d="M44 476 C48 362 116 300 200 300 C284 300 352 362 356 476 Z"/>
    <rect class="avatar-femme__peau" x="170" y="236" width="60" height="78" rx="24"/>
    <path class="avatar-femme__peau" d="M162 302 L200 342 L238 302 Z"/>
    <circle class="avatar-femme__peau" cx="134" cy="192" r="13"/>
    <circle class="avatar-femme__peau" cx="266" cy="192" r="13"/>
    <ellipse class="avatar-femme__peau" cx="200" cy="186" rx="67" ry="80"/>
    @if ($variante['coiffure'] === 3)
        {{-- Foulard noué, de la couleur de la tenue. --}}
        <path class="avatar-femme__tenue" d="M120 182 C108 98 156 68 204 70 C262 72 296 108 280 184 C266 144 238 128 200 128 C164 128 136 144 120 182 Z"/>
        <ellipse class="avatar-femme__tenue" cx="244" cy="80" rx="44" ry="26" transform="rotate(-18 244 80)"/>
        <ellipse class="avatar-femme__tenue" cx="170" cy="74" rx="34" ry="20" transform="rotate(16 170 74)"/>
    @else
        <path class="avatar-femme__cheveux" d="M130 188 C124 116 158 96 202 96 C248 96 280 120 270 188 C260 152 236 134 200 134 C168 134 142 150 130 188 Z"/>
        @if ($variante['coiffure'] === 2)
            <circle class="avatar-femme__cheveux" cx="200" cy="88" r="36"/>
        @endif
    @endif
    </g>
</svg>
