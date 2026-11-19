@props(['titre', 'ariane' => [], 'actif' => null])
<!DOCTYPE html>
<html lang="fr" data-theme="clair">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $titre }} · Femme modèle du Trésor</title>
    <script>try { const t = localStorage.getItem('fm-theme'); if (t) { document.documentElement.dataset.theme = t; } } catch (e) {}</script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<div class="app">
    <aside class="sidebar">
        <div class="sidebar__marque">
            <img src="{{ asset('images/embleme-tresor.png') }}" alt="Trésor public">
            <div class="sidebar__nom">Femme modèle<small>du Trésor · DGTCP</small></div>
        </div>
        <nav class="sidebar__nav" aria-label="Navigation principale">
            @foreach ($menus as $groupe => $liens)
                <div class="nav-groupe">{{ $groupe }}</div>
                @foreach ($liens as $lien)
                    <a href="{{ $lien['url'] }}" @class(['nav-lien', 'est-actif' => $lien['cle'] === $actif]) @if ($lien['cle'] === $actif) aria-current="page" @endif>
                        <i data-lucide="{{ $lien['icone'] }}"></i>{{ $lien['libelle'] }}
                    </a>
                @endforeach
            @endforeach
        </nav>
        <div class="sidebar__pied">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="nav-lien"><i data-lucide="log-out"></i>Se déconnecter</button>
            </form>
            <div class="sidebar__version">Plateforme de vote · v0.1</div>
        </div>
        <div class="bande-tricolore"></div>
    </aside>

    <div class="principal">
        <header class="entete">
            <nav class="fil-ariane" aria-label="Fil d'Ariane">
                <i data-lucide="house"></i>
                @foreach ($ariane as $element)
                    <i data-lucide="chevron-right"></i>
                    @if ($loop->last)<strong>{{ $element }}</strong>@else<span>{{ $element }}</span>@endif
                @endforeach
            </nav>
            <div class="entete__droite">
                @if ($affectationUtilisateur)
                    <span class="affectation"><i data-lucide="map-pin"></i>Affectation : <strong>{{ $affectationUtilisateur->structure->libelle }}</strong></span>
                @endif
                <button class="icone-bouton" data-basculer-theme title="Basculer le thème clair / sombre"><i data-lucide="moon"></i></button>
                <div class="profil">
                    <span class="avatar">{{ $initiales }}</span>
                    <div class="profil__nom">{{ $utilisateur->name }}<small>{{ $profil }}</small></div>
                </div>
            </div>
        </header>
        <main class="contenu">
            @if (session('succes'))
                <div class="alerte alerte--succes espace-bloc" role="status"><i data-lucide="circle-check"></i><div>{{ session('succes') }}</div></div>
            @endif
            @foreach (['edition', 'phase', 'scrutin', 'candidate', 'agent_id', 'date_fin', 'structure'] as $cleErreur)
                @error($cleErreur)
                    <div class="alerte alerte--erreur espace-bloc" role="alert"><i data-lucide="circle-alert"></i><div>{{ $message }}</div></div>
                @enderror
            @endforeach
            {{ $slot }}
        </main>
    </div>
</div>
{{ $modales ?? '' }}
</body>
</html>
