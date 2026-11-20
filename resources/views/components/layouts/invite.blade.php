@props(['titre', 'indexer' => false, 'editionEnCours' => null])
{{-- Gabarit des écrans hors connexion : panneau de la DGTCP à gauche, formulaire à droite. --}}
<!DOCTYPE html>
<html lang="fr" data-theme="clair">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $titre }} · Femme modèle du Trésor</title>
    <x-meta :titre="$titre.' · Femme modèle du Trésor'" :indexer="$indexer" />
    <script>try { const t = localStorage.getItem('fm-theme'); if (t) { document.documentElement.dataset.theme = t; } } catch (e) {}</script>
    {{-- Transition animée entre deux pages de ce gabarit (matricule, puis code) : voir .connexion__boite. --}}
    <style>@view-transition { navigation: auto; }</style>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body>
<div class="connexion">
    <section class="connexion__panneau">
        <div class="connexion__marque">
            <img src="{{ asset('images/embleme-tresor.png') }}" alt="" class="connexion__embleme">
            <div><strong>Direction générale du Trésor</strong><small>et de la Comptabilité publique</small></div>
        </div>
        <div class="connexion__accroche">
            @if ($editionEnCours ?? null)
                <div class="surtitre">Édition {{ $editionEnCours }}</div>
            @endif
            <h1>Élisez la Femme modèle du Trésor</h1>
            <p class="connexion__citation">Elles incarnent l’intégrité, l’engagement et l’excellence qui font, chaque jour, la force du Trésor public.</p>
        </div>
        <div class="connexion__mention">République du Bénin · {{ config('app.ministere') }}</div>
    </section>

    <section class="connexion__formulaire">
        <div class="connexion__boite">
            @if (session('status'))
                <x-notification :message="session('status')" />
            @endif
            @if ($errors->any())
                <div class="alerte alerte--erreur espace-bloc" role="alert"><i data-lucide="circle-alert"></i><div>{{ $errors->first() }}</div></div>
            @endif

            {{ $slot }}

            <div class="bande-tricolore connexion__bande"></div>
            <x-pied-de-page class="pied-de-page--connexion" />
        </div>
    </section>
</div>
@livewireScripts
</body>
</html>
