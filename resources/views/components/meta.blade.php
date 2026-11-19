@props([
    'titre',
    'description' => "Plateforme de vote électronique de la Direction générale du Trésor et de la Comptabilité publique pour l'élection de la Femme modèle du Trésor.",
    'indexer' => false,
])
{{-- Icônes, description et cartes de partage (Open Graph, Twitter) communes aux gabarits. --}}
<meta name="description" content="{{ $description }}">
<meta name="robots" content="{{ $indexer ? 'index, follow' : 'noindex, nofollow' }}">
<link rel="canonical" href="{{ url()->current() }}">

<link rel="icon" href="{{ asset('favicon.ico') }}" sizes="48x48">
<link rel="icon" type="image/png" href="{{ asset('favicon-96x96.png') }}" sizes="96x96">
<link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
<link rel="manifest" href="{{ asset('site.webmanifest') }}">

<meta property="og:type" content="website">
<meta property="og:site_name" content="Femme modèle du Trésor">
<meta property="og:locale" content="fr_FR">
<meta property="og:title" content="{{ $titre }}">
<meta property="og:description" content="{{ $description }}">
<meta property="og:url" content="{{ url()->current() }}">
<meta property="og:image" content="{{ asset('images/partage.png') }}">
<meta property="og:image:type" content="image/png">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:alt" content="Emblème du Trésor public et titre « Femme modèle du Trésor 2026, plateforme de vote électronique »">

<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $titre }}">
<meta name="twitter:description" content="{{ $description }}">
<meta name="twitter:image" content="{{ asset('images/partage.png') }}">
<meta name="twitter:image:alt" content="Emblème du Trésor public et titre « Femme modèle du Trésor 2026, plateforme de vote électronique »">
