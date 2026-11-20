<x-mail::layout>
{{-- Header --}}
<x-slot:header>
<x-mail::header :url="config('app.url')" :edition="$editionEnCours ?? null">
Femme modèle du Trésor
</x-mail::header>
</x-slot:header>

{{-- Body --}}
{!! $slot !!}

{{-- Subcopy --}}
@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{!! $subcopy !!}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

{{-- Footer --}}
<x-slot:footer>
<x-mail::footer>
{{ config('app.direction') }}<br>
République du Bénin · {{ config('app.ministere') }}

**© {{ date('Y') }} {{ config('app.copyright') }}**
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
