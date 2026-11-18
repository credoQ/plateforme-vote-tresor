@php
    $pages = [
        ['connexion', 'log-in', 'Connexion', 'Matricule et mot de passe, rappel de la phase ouverte.', 'Tous'],
        ['tableau-de-bord', 'layout-dashboard', 'Tableau de bord du comité', 'Phases, participation, suivi des scrutins, points à traiter.', "Comité d'organisation"],
        ['vote', 'vote', 'Bulletin de vote', 'Choix d’une candidate, confirmation et accusé de vote anonyme.', 'Électeur'],
        ['candidates', 'users-round', 'Candidates d’office', 'Liste d’une structure, désactivation avec motif et réactivation.', 'Point focal · Comité'],
        ['resultats', 'trophy', 'Résultats d’un scrutin', 'Lauréate, classement, participation, procès-verbal.', 'Comité · Observateur'],
        ['tokens', 'palette', 'Tokens et composants', 'Échelles du bleu Trésor et de l’or, neutres, états, typographie, boutons.', 'Équipe projet'],
    ];
@endphp
<x-layouts.maquette titre="Maquettes" :ariane="['Maquettes']" profil="comite" actif="">
    <div class="titre-page">
        <div>
            <div class="surtitre">Charte Kadoo 2.0 · bleu Trésor et or doré</div>
            <h1>Maquettes de la plateforme</h1>
            <p>Écrans de référence du projet « Femme modèle du Trésor ». Données fictives.</p>
        </div>
    </div>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:16px">
        @foreach ($pages as [$cle, $icone, $titre, $desc, $profil])
            <a href="{{ route('maquettes.page', $cle) }}" class="carte" style="padding:20px;text-decoration:none;color:inherit;display:flex;gap:14px">
                <span class="tuile__icone" style="width:40px;height:40px"><i data-lucide="{{ $icone }}"></i></span>
                <span><b style="font-size:15px">{{ $titre }}</b><br><span style="color:var(--tr-texte-2);font-size:13px">{{ $desc }}</span><br><span class="etiquette" style="margin-top:10px">{{ $profil }}</span></span>
            </a>
        @endforeach
    </div>
</x-layouts.maquette>
