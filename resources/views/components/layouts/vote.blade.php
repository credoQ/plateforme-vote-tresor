@props(['titre', 'contexte' => null])
{{-- Gabarit de la page de vote : sans menu, l'électeur n'a que son bulletin sous les yeux. Le vote se confirme dans le
     bulletin, par une fenêtre qui rappelle le choix (alertes.js). --}}
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
<div class="isoloir">
    <header class="isoloir__entete">
        <div class="isoloir__marque">
            <img src="{{ asset('images/embleme-tresor.png') }}" alt="Trésor public">
            <div><strong>Femme modèle du Trésor</strong>@if ($contexte)<small>{{ $contexte }}</small>@endif</div>
        </div>
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
@livewireScripts
</body>
</html>
