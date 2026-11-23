{{-- Donut en SVG (App\View\Components\Donut) : valeur et légende au centre ; la légende détaillée est à côté. --}}
<div {{ $attributes->class(['donut', 'donut--petit' => $petit]) }} role="img" aria-label="{{ $description }}">
    <svg viewBox="0 0 42 42" aria-hidden="true">
        <circle class="donut__fond" cx="21" cy="21" r="15.915"></circle>
        @foreach ($arcs as $arc)
            @if ($arc['part'] > 0)
                <circle class="donut__arc--{{ $arc['couleur'] }}" cx="21" cy="21" r="15.915" stroke-dasharray="{{ $arc['part'] }} {{ 100 - $arc['part'] }}" stroke-dashoffset="{{ $arc['decalage'] }}"></circle>
            @endif
        @endforeach
    </svg>
    <div class="donut__centre"><strong>{{ $valeur }}</strong>@if ($legende)<small>{{ $legende }}</small>@endif</div>
</div>
