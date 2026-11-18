@props(['titre', 'ariane' => [], 'actif' => null])
@php
    $utilisateur = auth()->user();
    $menus = array_filter([
        'Mon espace' => [
            ['espace', 'house', 'Accueil', route('espace'), true],
            ...($utilisateur->can('voter') ? [['vote', 'vote', 'Voter', route('vote'), true]] : []),
            ['palmares', 'trophy', 'Résultats', route('palmares'), true],
        ],
        'Pilotage' => array_values(array_filter([
            ['editions', 'calendar-range', 'Éditions', route('editions.index'), $utilisateur->can('piloter')],
        ], fn (array $lien): bool => $lien[4])),
        'Suivi' => array_values(array_filter([
            ['tableau-de-bord', 'layout-dashboard', 'Tableau de bord', route('tableau-de-bord'), $utilisateur->can('observer') || $utilisateur->can('controler-listes')],
            ['journal', 'shield-check', 'Journal d’audit', route('journal'), $utilisateur->can('observer')],
        ], fn (array $lien): bool => $lien[4])),
        'Administration' => array_values(array_filter([
            ['agents', 'id-card', $utilisateur->aLeRole(\App\Enums\Acces\CodeRole::Administrateur, \App\Enums\Acces\CodeRole::Comite, \App\Enums\Acces\CodeRole::Observateur) ? 'Agents' : 'Agents de ma structure', route('agents.index'), $utilisateur->can('viewAny', \App\Models\Agent::class)],
            ['utilisateurs', 'users', 'Utilisateurs', route('utilisateurs.index'), $utilisateur->can('administrer')],
            ['taches-echouees', 'rotate-ccw', 'Tâches en échec', route('taches-echouees.index'), $utilisateur->can('administrer')],
        ], fn (array $lien): bool => $lien[4])),
    ]);
    $initiales = collect(preg_split('/\s+/', trim($utilisateur->name)))->take(2)->map(fn ($mot) => mb_substr($mot, 0, 1))->implode('');
    $profil = $utilisateur->roles->first()?->code->libelle() ?? 'Utilisateur';
@endphp
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
                @if ($liens !== [])
                    <div class="nav-groupe">{{ $groupe }}</div>
                    @foreach ($liens as $lien)
                        <a href="{{ $lien[3] }}" @class(['nav-lien', 'est-actif' => $lien[0] === $actif]) @if ($lien[0] === $actif) aria-current="page" @endif>
                            <i data-lucide="{{ $lien[1] }}"></i>{{ $lien[2] }}
                        </a>
                    @endforeach
                @endif
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
                @if ($affectation = $utilisateur->agent?->affectationLe())
                    <span class="affectation"><i data-lucide="map-pin"></i>Affectation : <strong>{{ $affectation->structure->libelle }}</strong></span>
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
            @foreach (['edition', 'phase', 'scrutin', 'candidate', 'agent_id', 'date_fin'] as $cleErreur)
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
