@props(['url', 'edition' => null])
<tr>
<td class="header" align="center">
<a href="{{ $url }}" class="header-lien">
<img src="{{ asset('images/embleme-tresor.png') }}" class="logo" width="72" height="72" alt="Emblème du Trésor public">
<span class="header-nom">{{ $slot }}</span>
</a>
@if ($edition)
<span class="header-edition">Édition {{ $edition }}</span>
@endif
</td>
</tr>
