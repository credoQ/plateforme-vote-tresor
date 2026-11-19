@props(['titre' => 'Tableau de bord', 'ariane' => [], 'profil' => 'comite', 'actif' => 'tableau-de-bord'])
@php
    $menus = [
        'comite' => [
            'Pilotage' => [
                ['tableau-de-bord', 'layout-dashboard', 'Tableau de bord', route('maquettes.page', 'tableau-de-bord')],
                ['edition', 'calendar-range', 'Édition 2026', '#'],
                ['scrutins', 'clipboard-list', 'Scrutins', '#', 26],
            ],
            'Électeurs et candidates' => [
                ['listes', 'list-checks', 'Listes électorales', '#'],
                ['candidates', 'users-round', 'Candidates', route('maquettes.page', 'candidates')],
            ],
            'Résultats' => [
                ['resultats', 'trophy', 'Résultats', route('maquettes.page', 'resultats')],
                ['pv', 'file-text', 'Procès-verbaux', '#'],
            ],
            'Administration' => [
                ['referentiel', 'building-2', 'Référentiel des structures', '#'],
                ['agents', 'id-card', 'Agents', '#'],
                ['audit', 'shield-check', "Journal d'audit", '#'],
            ],
        ],
        'electeur' => [
            'Mon espace' => [
                ['vote', 'vote', 'Voter', route('maquettes.page', 'vote')],
                ['candidates', 'users-round', 'Candidates de mon scrutin', route('maquettes.page', 'vote')],
                ['resultats', 'trophy', 'Résultats publiés', route('maquettes.page', 'resultats')],
            ],
        ],
    ];
    $initiales = $profil === 'electeur' ? 'AH' : 'KD';
@endphp
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
<div class="app">
    <aside class="sidebar" id="navigation">
        <div class="sidebar__marque">
            <img src="{{ asset('images/embleme-tresor.png') }}" alt="Trésor public">
            <div class="sidebar__nom">Femme modèle<small>du Trésor · DGTCP</small></div>
            <button type="button" class="icone-bouton sidebar__fermer" data-fermer-menu aria-label="Fermer le menu"><i data-lucide="x"></i></button>
        </div>
        <nav class="sidebar__nav" aria-label="Navigation principale">
            @foreach ($menus[$profil] as $groupe => $liens)
                <div class="nav-groupe">{{ $groupe }}</div>
                @foreach ($liens as $l)
                    <a href="{{ $l[3] }}" @class(['nav-lien', 'est-actif' => $l[0] === $actif]) @if ($l[0] === $actif) aria-current="page" @endif>
                        <i data-lucide="{{ $l[1] }}"></i>{{ $l[2] }}
                        @isset($l[4])<span class="compteur">{{ $l[4] }}</span>@endisset
                    </a>
                @endforeach
            @endforeach
        </nav>
        <div class="sidebar__pied">
            <a href="#" class="nav-lien"><i data-lucide="life-buoy"></i>Aide &amp; signalement</a>
            <div class="sidebar__version">Plateforme de vote · v0.1</div>
        </div>
        <div class="bande-tricolore"></div>
    </aside>
    <div class="voile-menu" data-fermer-menu></div>

    <div class="principal">
        <header class="entete">
            <button type="button" class="icone-bouton entete__menu" data-ouvrir-menu aria-controls="navigation" aria-expanded="false" aria-label="Ouvrir le menu"><i data-lucide="menu"></i></button>
            <nav class="fil-ariane" aria-label="Fil d'Ariane">
                <i data-lucide="house"></i>
                @foreach ($ariane as $element)
                    <i data-lucide="chevron-right"></i>
                    @if ($loop->last)<strong>{{ $element }}</strong>@else<span>{{ $element }}</span>@endif
                @endforeach
            </nav>
            <div class="entete__droite">
                <span class="affectation"><i data-lucide="map-pin"></i>
                    @if ($profil === 'electeur') Affectation : <strong>TC Abomey-Calavi</strong>@else Édition : <strong>2026 · Phase 1</strong>@endif
                </span>
                <button class="icone-bouton" data-basculer-theme title="Basculer le thème clair / sombre"><i data-lucide="moon"></i></button>
                <button class="icone-bouton" title="Notifications"><i data-lucide="bell"></i><span class="point"></span></button>
                <div class="profil">
                    <span class="avatar">{{ $initiales }}</span>
                    <div class="profil__nom">
                        @if ($profil === 'electeur') HOUNSOU Afiavi<small>Électrice · Atlantique</small>@else KPADONOU Damien<small>Comité d'organisation</small>@endif
                    </div>
                    <i data-lucide="chevron-down" style="width:16px;height:16px;color:var(--tr-texte-3)"></i>
                </div>
            </div>
        </header>
        <main class="contenu">
            {{ $slot }}
        </main>
    </div>
</div>
{{ $modales ?? '' }}
@livewireScripts
</body>
</html>
