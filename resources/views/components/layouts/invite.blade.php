@props(['titre', 'indexer' => false, 'editionEnCours' => null, 'videos' => []])
{{-- Gabarit des écrans hors connexion : panneau photo de la DGTCP à gauche (bandeau en haut sur téléphone), formulaire à droite. --}}
<!DOCTYPE html>
<html lang="fr" data-theme="clair">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $titre }} · Femme modèle du Trésor</title>
    <x-meta :titre="$titre.' · Femme modèle du Trésor'" :indexer="$indexer" />
    <script>try { const t = localStorage.getItem('fm-theme'); if (t) { document.documentElement.dataset.theme = t; } } catch (e) {}</script>
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
        <div class="connexion__bas">
            <div class="connexion__accroche">
                @if ($editionEnCours)
                    <div class="surtitre">Édition {{ $editionEnCours }}</div>
                @endif
                <h1>Élisez la Femme modèle du Trésor</h1>
                <p class="connexion__citation">Elles incarnent l’intégrité, l’engagement et l’excellence qui font, chaque jour, la force du Trésor public.</p>
            </div>
            @if ($videos)
                <div class="connexion__videos">
                    @foreach ($videos as $video)
                        <button type="button" class="video-carte" x-data x-on:click="$dispatch('ouvrir-video', {{ Js::from($video) }})">
                            <span class="video-carte__vignette">
                                <img src="{{ $video['apercu'] }}" alt="" loading="lazy">
                                <span class="video-carte__lecture"><span><i data-lucide="play"></i></span></span>
                            </span>
                            <span><strong>{{ $video['titre'] }}</strong><small>{{ $video['detail'] }}</small></span>
                        </button>
                    @endforeach
                </div>
            @endif
        </div>
        <div class="connexion__mention">République du Bénin · {{ config('app.ministere') }}</div>
    </section>

    <div class="connexion__bandeau">
        <div class="connexion__bandeau-texte">
            @if ($editionEnCours)
                <div class="surtitre">Édition {{ $editionEnCours }}</div>
            @endif
            <strong>Élisez la Femme modèle du Trésor</strong>
        </div>
        <div class="bande-tricolore"></div>
    </div>

    <section class="connexion__formulaire">
        <div class="connexion__boite">
            @if (session('status'))
                <x-notification :message="session('status')" />
            @endif
            @if ($errors->any())
                <div class="alerte alerte--erreur espace-bloc" role="alert"><i data-lucide="circle-alert"></i><div>{{ $errors->first() }}</div></div>
            @endif

            {{ $slot }}

            @if ($videos)
                <div class="connexion__videos-telephone">
                    @foreach ($videos as $video)
                        <button type="button" x-data x-on:click="$dispatch('ouvrir-video', {{ Js::from($video) }})"><i data-lucide="circle-play"></i>{{ $video['titre'] }} en vidéo</button>
                    @endforeach
                </div>
            @endif

            <div class="bande-tricolore connexion__bande"></div>
            <x-pied-de-page class="pied-de-page--connexion" />
        </div>
    </section>
</div>

@if ($videos)
    <div x-data="lecteurVideo" x-on:ouvrir-video.window="ouvrir($event.detail)" x-on:keydown.escape.window="fermer()">
        <div class="lecteur" x-show="ouvert" x-cloak x-transition.opacity x-on:click.self="fermer()" role="dialog" aria-modal="true" x-bind:aria-label="video.titre">
            <div class="lecteur__boite">
                <div class="lecteur__tete">
                    <span x-text="video.titre"></span>
                    <button type="button" class="lecteur__fermer" x-ref="fermer" x-on:click="fermer()" aria-label="Fermer la vidéo"><i data-lucide="x"></i></button>
                </div>
                <video x-ref="video" class="lecteur__video" controls playsinline preload="none" x-bind:src="video.src" x-bind:poster="video.apercu"></video>
            </div>
        </div>
    </div>
@endif
@livewireScripts
</body>
</html>
