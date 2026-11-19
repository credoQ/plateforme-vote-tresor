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
Direction générale du Trésor et de la Comptabilité publique<br>
République du Bénin · Ministère de l'Économie et des Finances

**© DIA/DGTCP {{ date('Y') }}**
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
