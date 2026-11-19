@props(['libelle' => 'Votre code', 'validite' => null])
{{ $libelle }} : {{ $slot }}
@if ($validite)
{{ $validite }}
@endif
