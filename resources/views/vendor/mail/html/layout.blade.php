<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="fr">
<head>
<title>{{ config('app.name') }}</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<meta name="color-scheme" content="light">
<meta name="supported-color-schemes" content="light">
<style>
@media only screen and (max-width: 600px) {
.inner-body {
width: 100% !important;
}

.content-cell, .header, .footer-cell {
padding-left: 20px !important;
padding-right: 20px !important;
}
}

@media only screen and (max-width: 500px) {
.button {
width: 100% !important;
}
}
</style>
{!! $head ?? '' !!}
</head>
<body>
{{-- Modèle A de la charte : bande tricolore en tête, emblème centré, pied de page DGTCP, dans une carte de 570 px. --}}
<table class="wrapper" width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td align="center">
<table class="inner-body" align="center" width="570" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td class="tricolore">
<table width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td class="tricolore-vert" width="33%" height="4"></td>
<td class="tricolore-jaune" width="34%" height="4"></td>
<td class="tricolore-rouge" width="33%" height="4"></td>
</tr>
</table>
</td>
</tr>

{!! $header ?? '' !!}

<tr>
<td class="content-cell">
{!! Illuminate\Mail\Markdown::parse($slot) !!}

{!! $subcopy ?? '' !!}
</td>
</tr>

{!! $footer ?? '' !!}
</table>
</td>
</tr>
</table>
</body>
</html>
