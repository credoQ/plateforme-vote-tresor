@php
    $scrutins = [
        ['PH1-DEP-ATL', 'Atlantique', 'Pôle départemental', 412, 291, 38, 'Ouvert'],
        ['PH1-DEP-LIT', 'Littoral', 'Pôle départemental', 356, 244, 31, 'Ouvert'],
        ['PH1-STR-TGE', "Trésorerie générale de l'État", 'Pôle structures', 188, 151, 24, 'Ouvert'],
        ['PH1-DEP-BOR', 'Borgou', 'Pôle départemental', 233, 109, 19, 'Ouvert'],
        ['PH1-STR-DIA', "Direction de l'Informatique et des Archives", 'Pôle structures', 64, 64, 9, 'Clôturé'],
        ['PH1-STR-DMG', 'Direction des Moyens généraux', 'Pôle structures', 41, 0, 0, 'Sans scrutin'],
    ];
    $badge = ['Ouvert' => 'primaire', 'Clôturé' => 'succes', 'Sans scrutin' => ''];
@endphp
<x-layouts.maquette titre="Tableau de bord" :ariane="['Pilotage', 'Tableau de bord']" profil="comite" actif="tableau-de-bord">
    <div class="titre-page">
        <div>
            <div class="surtitre">Édition 2026</div>
            <h1>Tableau de bord du comité</h1>
            <p>Phase 1 · élections primaires, ouverte du 18 au 28 novembre.</p>
        </div>
        <div class="titre-page__actions">
            <div class="segment"><button>Aujourd'hui</button><button class="est-actif">Phase 1</button><button>Édition</button></div>
            <button class="btn btn-secondaire"><i data-lucide="download"></i>Exporter</button>
            <button class="btn btn-primaire"><i data-lucide="clipboard-list"></i>Clôturer la phase</button>
        </div>
    </div>

    <div class="carte" style="padding:20px 24px;margin-bottom:20px">
        <div class="etapes">
            <div class="etape est-en-cours"><span class="etape__puce">1</span><span class="etape__texte">Primaires<small>26 scrutins · en cours</small></span></div>
            <span class="etape__trait"></span>
            <div class="etape"><span class="etape__puce">2</span><span class="etape__texte">Demi-finales<small>2 scrutins · à partir du 2 déc.</small></span></div>
            <span class="etape__trait"></span>
            <div class="etape"><span class="etape__puce">3</span><span class="etape__texte">Finale<small>1 scrutin · tous les agents</small></span></div>
        </div>
    </div>

    <div class="grille-tuiles">
        <div class="tuile tuile--heros">
            <div class="tuile__haut"><span class="surtitre">Taux de participation · phase 1</span><span class="tuile__icone"><i data-lucide="percent"></i></span></div>
            <div class="tuile__valeur">63,4 <small>%</small></div>
            <div class="tuile__detail"><span class="tendance tendance--hausse"><i data-lucide="arrow-up-right"></i>+8,2 pts</span>depuis hier · 2 914 votants sur 4 596</div>
        </div>
        <div class="tuile">
            <div class="tuile__haut"><span class="surtitre">Scrutins ouverts</span><span class="tuile__icone"><i data-lucide="clipboard-list"></i></span></div>
            <div class="tuile__valeur">23 <small>/ 26</small></div>
            <div class="tuile__detail">3 clôturés · 2 structures sans scrutin</div>
        </div>
        <div class="tuile">
            <div class="tuile__haut"><span class="surtitre">Candidates actives</span><span class="tuile__icone"><i data-lucide="users-round"></i></span></div>
            <div class="tuile__valeur">1 287</div>
            <div class="tuile__detail">dont 41 désistements</div>
        </div>
        <div class="tuile">
            <div class="tuile__haut"><span class="surtitre">Égalités à départager</span><span class="tuile__icone" style="background:var(--tr-attente-50);color:var(--tr-attente-700)"><i data-lucide="alert-triangle"></i></span></div>
            <div class="tuile__valeur">1</div>
            <div class="tuile__detail">Scrutin PH1-STR-DIA</div>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;align-items:start">
        <section class="carte">
            <div class="carte__entete"><h2>Suivi des scrutins</h2><a href="#" class="lien">Tous les scrutins<i data-lucide="arrow-right"></i></a></div>
            <div class="barre-filtres">
                <div class="champ-icone"><i data-lucide="search"></i><input class="champ" placeholder="Rechercher un scrutin, une structure"></div>
                <select class="champ"><option>Tous les pôles</option><option>Pôle départemental</option><option>Pôle structures</option></select>
                <select class="champ"><option>Tous les statuts</option><option>Ouvert</option><option>Clôturé</option></select>
            </div>
            <div class="tableau-defilant">
                <table class="tableau">
                    <thead><tr><th>Scrutin</th><th>Zone de vote</th><th class="nombre">Inscrits</th><th>Participation</th><th>Statut</th><th class="actions"></th></tr></thead>
                    <tbody>
                    @foreach ($scrutins as [$code, $nom, $pole, $inscrits, $votants, $cand, $statut])
                        @php $taux = $inscrits ? round($votants / $inscrits * 100) : 0; @endphp
                        <tr>
                            <td class="reference">{{ $code }}</td>
                            <td>{{ $nom }}<div style="font-size:12px;color:var(--tr-texte-3)">{{ $pole }} · {{ $cand }} candidates</div></td>
                            <td class="nombre">{{ number_format($inscrits, 0, ',', ' ') }}</td>
                            <td>@if ($statut === 'Sans scrutin')<span style="color:var(--tr-texte-3);font-size:12px">Aucune agent femme en service</span>@else<div class="cellule-progression"><div class="progression"><span style="width:{{ $taux }}%"></span></div><b>{{ $taux }} %</b></div>@endif</td>
                            <td><span class="badge {{ $badge[$statut] ? 'badge--'.$badge[$statut] : '' }}">{{ $statut }}</span></td>
                            <td class="actions"><button class="btn btn-discret btn--icone" title="Consulter"><i data-lucide="eye"></i></button></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="pagination">1 à 6 sur 26 scrutins<div class="pagination__pages"><button class="btn btn-secondaire btn--sm">Précédent</button><button class="btn btn-secondaire btn--sm">Suivant</button></div></div>
        </section>

        <div style="display:flex;flex-direction:column;gap:20px">
            <section class="carte">
                <div class="carte__entete"><h2>Participation par pôle</h2></div>
                <div class="carte__corps">
                    <div class="barres">
                        <div class="barre"><span>Départemental</span><div class="barre__piste"><span style="width:61%;background:var(--tr-graph-1)"></span></div><b>61 %</b></div>
                        <div class="barre"><span>Structures</span><div class="barre__piste"><span style="width:72%;background:var(--tr-graph-2)"></span></div><b>72 %</b></div>
                    </div>
                    <div class="legende" style="margin-top:16px"><span><i style="background:var(--tr-graph-1)"></i>Pôle départemental</span><span><i style="background:var(--tr-graph-2)"></i>Pôle structures</span></div>
                </div>
            </section>
            <section class="carte">
                <div class="carte__entete"><h2>À traiter</h2></div>
                <div class="carte__corps" style="display:flex;flex-direction:column;gap:12px">
                    <div class="alerte alerte--attente"><i data-lucide="alert-triangle"></i><div><strong>Égalité en tête</strong> · DIA : 2 candidates à 21 voix. Modalité de départage à appliquer.</div></div>
                    <div class="alerte alerte--info"><i data-lucide="info"></i><div><strong>2 structures sans scrutin</strong> : aucune agent femme en service à la date de gel.</div></div>
                </div>
            </section>
        </div>
    </div>
</x-layouts.maquette>
