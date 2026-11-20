@props(['titre', 'actif' => 'espace', 'ariane' => []])
{{-- Gabarit de l'espace électeur : barre en haut (onglets en bas sur téléphone), contenu centré, sans menu de gestion. --}}
<!DOCTYPE html>
<html lang="fr" data-theme="clair">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $titre }} · Femme modèle du Trésor</title>
    <x-meta :titre="$titre.' · Femme modèle du Trésor'" />
    <script>try { const t = localStorage.getItem('fm-theme'); if (t) { document.documentElement.dataset.theme = t; } } catch (e) {}</script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body>
<div class="portail">
    <header class="portail__entete">
        <a href="{{ route('espace') }}" class="portail__marque">
            <img src="{{ asset('images/embleme-tresor.png') }}" alt="">
            <span><strong>Femme modèle du Trésor</strong><small>DGTCP{{ $editionEnCours ? ' · Édition '.$editionEnCours : '' }}</small></span>
        </a>
        <nav class="portail__nav" aria-label="Navigation principale">
            <a href="{{ route('espace') }}" @class(['est-actif' => $actif === 'espace']) @if ($actif === 'espace') aria-current="page" @endif><i data-lucide="house"></i>Mon espace</a>
            <a href="{{ route('palmares') }}" @class(['est-actif' => $actif === 'palmares']) @if ($actif === 'palmares') aria-current="page" @endif><i data-lucide="trophy"></i>Résultats</a>
        </nav>
        <div class="portail__droite">
            @if ($consoleDeGestion)
                <a href="{{ $consoleDeGestion }}" class="btn btn-discret portail__console" title="Console de gestion"><i data-lucide="layout-dashboard"></i><span>Console de gestion</span></a>
            @endif
            <button type="button" class="icone-bouton" data-basculer-theme title="Basculer le thème clair / sombre"><i data-lucide="moon"></i></button>
            <div class="profil portail__profil">
                <span class="avatar">{{ $initiales }}</span>
                <div class="profil__nom">{{ $utilisateur->name }}<small>Matricule {{ $utilisateur->login }}</small></div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="icone-bouton" title="Se déconnecter" aria-label="Se déconnecter"><i data-lucide="log-out"></i></button>
                </form>
            </div>
        </div>
    </header>
    <div class="bande-tricolore"></div>

    <main class="portail__contenu">
        @if (session('succes'))
            <x-notification :message="session('succes')" />
        @endif
        @foreach (['candidate', 'scrutin', 'motif'] as $cleErreur)
            @error($cleErreur)
                <div class="alerte alerte--erreur" role="alert"><i data-lucide="circle-alert"></i><div>{{ $message }}</div></div>
            @enderror
        @endforeach
        {{ $slot }}
    </main>

    <x-pied-de-page class="portail__pied" />
    <nav class="portail__onglets" aria-label="Navigation principale">
        <a href="{{ route('espace') }}" @class(['est-actif' => $actif === 'espace'])><i data-lucide="house"></i>Mon espace</a>
        <a href="{{ route('palmares') }}" @class(['est-actif' => $actif === 'palmares'])><i data-lucide="trophy"></i>Résultats</a>
    </nav>
</div>
{{ $modales ?? '' }}
@livewireScripts
</body>
</html>
