@props(['titre', 'ariane' => [], 'actif' => null, 'centre' => false])
<!DOCTYPE html>
<html lang="fr" data-theme="clair">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $titre }} · Femme modèle du Trésor</title>
    <x-meta :titre="$titre.' · Femme modèle du Trésor'" />
    <script>document.documentElement.classList.add('js'); try { const t = localStorage.getItem('fm-theme'); if (t) { document.documentElement.dataset.theme = t; } if (localStorage.getItem('fm-menu') === 'replie') { document.documentElement.classList.add('menu-replie'); } } catch (e) {}</script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body>
<div class="app">
    <aside class="sidebar" id="navigation">
        <div class="sidebar__marque">
            <img src="{{ asset('images/embleme-tresor.png') }}" alt="Trésor public">
            <div class="sidebar__nom">Femme modèle<small>du Trésor · DGTCP</small></div>
            <button type="button" class="icone-bouton sidebar__fermer" data-fermer-menu aria-label="Fermer le menu"><i data-lucide="x"></i></button>
        </div>
        <nav class="sidebar__nav" aria-label="Navigation principale">
            @foreach ($menus as $groupe => $liens)
                <div class="nav-groupe">{{ $groupe }}</div>
                @foreach ($liens as $lien)
                    <a href="{{ $lien['url'] }}" @class(['nav-lien', 'est-actif' => $lien['cle'] === $actif]) data-infobulle="{{ $lien['libelle'] }}" @if ($lien['cle'] === $actif) aria-current="page" @endif @if ($lien['cle'] !== 'vote') wire:navigate @endif>
                        <i data-lucide="{{ $lien['icone'] }}"></i><span class="nav-lien__libelle">{{ $lien['libelle'] }}</span>
                    </a>
                @endforeach
            @endforeach
        </nav>
        <div class="sidebar__pied">
            <div class="sidebar__version">Plateforme de vote · v0.1</div>
        </div>
        <div class="bande-tricolore"></div>
    </aside>
    <div class="voile-menu" data-fermer-menu></div>

    <div class="principal">
        <header class="entete">
            <button type="button" class="icone-bouton entete__menu" data-basculer-menu aria-controls="navigation" aria-expanded="true" aria-label="Replier le menu"><i data-lucide="menu"></i></button>
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
                <div class="menu-profil" x-data="{ ouvert: false }" x-on:click.outside="ouvert = false" x-on:keydown.escape.window="ouvert = false">
                    <button type="button" class="profil" x-on:click="ouvert = ! ouvert" x-bind:aria-expanded="ouvert" aria-haspopup="menu" aria-controls="menu-profil">
                        <span class="avatar">{{ $initiales }}</span>
                        <span class="profil__nom">{{ $utilisateur->name }}<small>{{ $profil }}</small></span>
                        <i data-lucide="chevron-down" class="profil__chevron"></i>
                    </button>
                    <div class="menu-profil__panneau" id="menu-profil" role="menu" x-show="ouvert" x-transition.opacity.duration.150ms x-cloak>
                        <div class="menu-profil__entete">
                            <strong>{{ $utilisateur->name }}</strong>
                            <small>{{ $profil }} · {{ $utilisateur->login }}</small>
                        </div>
                        <div class="menu-profil__groupe">
                            @if ($espaceElecteur)
                                <a wire:navigate href="{{ $espaceElecteur }}" class="menu-profil__lien" role="menuitem"><i data-lucide="house"></i>Mon espace électeur</a>
                            @endif
                            <button type="button" class="menu-profil__lien" role="menuitem" data-basculer-theme><i data-lucide="moon"></i>Thème sombre<span class="interrupteur" aria-hidden="true"></span></button>
                        </div>
                        <form method="POST" action="{{ route('logout') }}" class="menu-profil__groupe" data-rechargement>
                            @csrf
                            <button type="submit" class="menu-profil__lien menu-profil__deconnexion" role="menuitem"><i data-lucide="log-out"></i>Se déconnecter</button>
                        </form>
                    </div>
                </div>
            </div>
        </header>
        <main @class(['contenu', 'contenu--centre' => $centre]) data-sans-rechargement>
            @if (session('succes'))
                <x-notification :message="session('succes')" />
            @endif
            @foreach (['edition', 'phase', 'scrutin', 'candidate', 'agent_id', 'date_fin', 'structure', 'role_electeur'] as $cleErreur)
                @error($cleErreur)
                    <x-erreur :message="$message" />
                @enderror
            @endforeach
            {{ $slot }}
        </main>
        <x-pied-de-page class="principal__pied" />
    </div>
</div>
{{ $modales ?? '' }}
@livewireScripts
</body>
</html>
