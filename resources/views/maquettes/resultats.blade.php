@php
    $classement = [['AHOUANDJINOU Sandrine', 'TC Ouidah', 87], ['GBAGUIDI Florence', 'TD Atlantique', 64], ['ADJOVI Mireille', 'TC Abomey-Calavi', 52], ['KOUTON Edwige', 'TC Zè', 31], ['DOSSOU Prisca', 'TC Allada', 22]];
    $exprimes = 279; $max = 87;
@endphp
<x-layouts.maquette titre="Résultats" :ariane="['Résultats', 'Scrutin départemental · Atlantique']" profil="comite" actif="resultats">
    <div class="titre-page">
        <div>
            <div class="surtitre">Phase 1 · PH1-DEP-ATL</div>
            <h1>Résultats · scrutin départemental Atlantique</h1>
            <p>Clôturé le 28 nov. 2026 à 18 h 00 · résultats validés par le comité d'organisation.</p>
        </div>
        <div class="titre-page__actions">
            <span class="badge badge--succes">Publié</span>
            <button class="btn btn-secondaire"><i data-lucide="file-down"></i>Procès-verbal (PDF)</button>
            <button class="btn btn-primaire"><i data-lucide="award"></i>Qualifier pour la phase 2</button>
        </div>
    </div>

    <div class="grille-tuiles">
        <div class="tuile tuile--heros">
            <div class="tuile__haut"><span class="surtitre">Lauréate départementale</span><span class="tuile__icone"><i data-lucide="crown"></i></span></div>
            <div style="display:flex;align-items:center;gap:12px;position:relative;z-index:1">
                <span class="avatar avatar--grand">AS</span>
                <div><div style="font-size:18px;line-height:26px;font-weight:700">AHOUANDJINOU Sandrine</div><div class="tuile__detail">TC Ouidah · 87 voix · 31,2 %</div></div>
            </div>
        </div>
        <div class="tuile"><div class="tuile__haut"><span class="surtitre">Inscrits</span><span class="tuile__icone"><i data-lucide="users"></i></span></div><div class="tuile__valeur">412</div><div class="tuile__detail">agents de la TD et des TC</div></div>
        <div class="tuile"><div class="tuile__haut"><span class="surtitre">Votants</span><span class="tuile__icone"><i data-lucide="vote"></i></span></div><div class="tuile__valeur">291 <small>· 70,6 %</small></div><div class="tuile__detail">12 bulletins blancs</div></div>
        <div class="tuile"><div class="tuile__haut"><span class="surtitre">Candidates</span><span class="tuile__icone"><i data-lucide="users-round"></i></span></div><div class="tuile__valeur">38</div><div class="tuile__detail">2 désactivées avant l'ouverture</div></div>
    </div>

    <section class="carte">
        <div class="carte__entete"><h2>Classement</h2><span style="margin-left:auto;font-size:12px;color:var(--tr-texte-3)">{{ $exprimes }} suffrages exprimés · 5 premières sur 38</span></div>
        <div class="tableau-defilant">
            <table class="tableau">
                <thead><tr><th style="width:64px">Rang</th><th>Candidate</th><th style="width:40%">Voix</th><th class="nombre">%</th><th>Statut</th></tr></thead>
                <tbody>
                @foreach ($classement as $i => [$nom, $structure, $voix])
                    <tr>
                        <td><b class="chiffres">{{ $i + 1 }}</b></td>
                        <td><div class="cellule-personne"><span @class(['avatar', 'avatar--sans-anneau' => $i > 0])>{{ collect(explode(' ', $nom))->map(fn ($m) => mb_substr($m, 0, 1))->take(2)->implode('') }}</span><div>{{ $nom }}<small>{{ $structure }}</small></div></div></td>
                        <td><div class="cellule-progression"><div class="progression {{ $i === 0 ? 'progression--jaune' : '' }}"><span style="width:{{ round($voix / $max * 100) }}%"></span></div><b>{{ $voix }}</b></div></td>
                        <td class="nombre">{{ number_format($voix / $exprimes * 100, 1, ',', ' ') }}</td>
                        <td>@if ($i === 0)<span class="badge badge--jaune">Lauréate</span>@else<span class="badge">Classée</span>@endif</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </section>
</x-layouts.maquette>
