@props(['libelle' => 'Votre code', 'validite' => null])
<table class="code" width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td class="code-cell" align="center">
<p class="code-libelle">{{ $libelle }}</p>
<p class="code-valeur">{{ $slot }}</p>
@if ($validite)
<p class="code-validite">{{ $validite }}</p>
@endif
</td>
</tr>
</table>
