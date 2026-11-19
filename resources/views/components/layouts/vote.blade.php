@props(['titre', 'contexte' => null, 'etape' => null])
{{-- Gabarit de la page de vote : sans menu, l'électeur n'a que son bulletin sous les yeux. Étapes : 1 choisir, 2 confirmer. --}}
<!DOCTYPE html>
<html lang="fr" data-theme="clair">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $titre }} · Femme modèle du Trésor</title>
    <x-meta :titre="$titre.' · Femme modèle du Trésor'" />
    <script>try { const t = localStorage.getItem('fm-theme'); if (t) { document.documentElement.dataset.theme = t; } } catch (e) {}</script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<div class="isoloir">
    <header class="isoloir__entete">
        <div class="isoloir__marque">
            <img src="{{ asset('images/embleme-tresor.png') }}" alt="Trésor public">
            <div><strong>Femme modèle du Trésor</strong>@if ($contexte)<small>{{ $contexte }}</small>@endif</div>
        </div>
        @if ($etape)
            <div class="etapes isoloir__etapes" aria-label="Étape {{ $etape }} sur 2">
                <div @class(['etape', 'est-en-cours' => $etape === 1, 'est-faite' => $etape === 2])>
                    <span class="etape__puce">@if ($etape === 2)<i data-lucide="check"></i>@else 1 @endif</span>
                    <span class="etape__texte">Choisir<small>votre candidate</small></span>
                </div>
                <span class="etape__trait"></span>
                <div @class(['etape', 'est-en-cours' => $etape === 2]) @if ($etape === 2) aria-current="step" @endif>
                    <span class="etape__puce">2</span>
                    <span class="etape__texte">Confirmer<small>votre vote</small></span>
                </div>
            </div>
        @endif
        <div class="isoloir__droite">
            <button type="button" class="icone-bouton" data-basculer-theme title="Basculer le thème clair / sombre"><i data-lucide="moon"></i></button>
            <a href="{{ route('espace') }}" class="btn btn-discret isoloir__quitter" title="Quitter le vote et revenir à l’accueil"><i data-lucide="x"></i><span>Quitter</span></a>
        </div>
    </header>
    <div class="bande-tricolore"></div>

    <main class="isoloir__contenu">
        @foreach (['scrutin', 'candidate', 'candidate_id'] as $cleErreur)
            @error($cleErreur)
                <div class="alerte alerte--erreur espace-bloc" role="alert"><i data-lucide="circle-alert"></i><div>{{ $message }}</div></div>
            @enderror
        @endforeach
        {{ $slot }}
    </main>
    <x-pied-de-page class="isoloir__pied" />
</div>
{{ $modales ?? '' }}
</body>
</html>
