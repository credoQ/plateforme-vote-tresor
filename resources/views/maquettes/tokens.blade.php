@php
    $t = json_decode(file_get_contents(base_path('design/tokens/tresor-tokens.json')), true);
    $c = $t['couleurs'];
@endphp
<x-layouts.maquette titre="Tokens" :ariane="['Charte', 'Tokens et composants']" profil="comite" actif="">
    <div class="titre-page">
        <div>
            <div class="surtitre">Charte Kadoo 2.0 · bleu Trésor et or doré</div>
            <h1>Tokens et composants</h1>
            <p>Couleur de base {{ $t['primaire']['base'] }} (remplace le vert Kadoo {{ $t['primaire']['remplace'] }}). Source : design/tokens/tresor-tokens.json.</p>
        </div>
        <div class="titre-page__actions"><button class="btn btn-secondaire" data-basculer-theme><i data-lucide="moon"></i>Thème sombre</button></div>
    </div>

    <div style="display:grid;gap:20px">
        <section class="carte">
            <div class="carte__entete"><h2>Échelle du bleu Trésor</h2><span class="etiquette" style="margin-left:auto">600 = couleur de base</span></div>
            <div class="carte__corps" style="display:grid;grid-template-columns:repeat(12,1fr);gap:8px">
                @foreach ($c['bleu'] as $niveau => $hex)
                    <div>
                        <div style="height:64px;border-radius:8px;background:{{ $hex }};{{ $niveau === '600' ? 'box-shadow:0 0 0 3px var(--tr-accent)' : 'border:1px solid var(--tr-bordure-legere)' }}"></div>
                        <div style="font-size:12px;font-weight:700;margin-top:6px">{{ $niveau }}</div><div class="chiffres" style="font-size:11px;color:var(--tr-texte-3)">{{ $hex }}</div>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="carte">
            <div class="carte__entete"><h2>Neutres teintés, accent or et couleurs d'état</h2></div>
            <div class="carte__corps" style="display:grid;gap:16px">
                <div style="display:grid;grid-template-columns:repeat(12,1fr);gap:8px">
                    @foreach ($c['gris'] as $niveau => $hex)
                        <div><div style="height:40px;border-radius:8px;background:{{ $hex }};border:1px solid var(--tr-bordure-legere)"></div><div style="font-size:11px;margin-top:4px"><b>{{ $niveau }}</b> <span class="chiffres" style="color:var(--tr-texte-3)">{{ $hex }}</span></div></div>
                    @endforeach
                </div>
                <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:16px">
                    @foreach (['or' => 'Accent or doré', 'succes' => 'Succès', 'attente' => 'Attente', 'erreur' => 'Erreur', 'info' => 'Info'] as $fam => $nom)
                        <div><div class="surtitre" style="margin-bottom:6px">{{ $nom }}</div><div style="display:flex;gap:4px">@foreach ($c[$fam] as $n => $hex)<div title="{{ $fam }}-{{ $n }} {{ $hex }}" style="flex:1;height:36px;border-radius:6px;background:{{ $hex }}"></div>@endforeach</div></div>
                    @endforeach
                </div>
                <div><div class="surtitre" style="margin-bottom:6px">Graphiques (ordre fixe)</div><div class="legende">@foreach ($c['graphiques'] as [$nom, $hex])<span><i style="background:{{ $hex }}"></i>{{ $nom }} {{ $hex }}</span>@endforeach</div></div>
            </div>
        </section>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
            <section class="carte">
                <div class="carte__entete"><h2>Typographie · Montserrat</h2></div>
                <div class="carte__corps" style="display:grid;gap:10px">
                    <div style="font-size:32px;line-height:40px;font-weight:700">Display 32/40</div>
                    <div style="font-size:24px;line-height:32px;font-weight:700">Titre H1 24/32</div>
                    <div style="font-size:18px;line-height:26px;font-weight:600">Titre H2 18/26</div>
                    <div style="font-size:15px;line-height:22px;font-weight:600">Titre H3 15/22</div>
                    <div>Corps 14/22 · texte courant de l'interface.</div>
                    <div style="font-size:13px;font-weight:500">Libellé 13/18</div>
                    <div class="surtitre">Surtitre 11/16</div>
                </div>
            </section>
            <section class="carte">
                <div class="carte__entete"><h2>Boutons, badges, champs</h2></div>
                <div class="carte__corps" style="display:grid;gap:16px">
                    <div style="display:flex;gap:8px;flex-wrap:wrap">
                        <button class="btn btn-primaire"><i data-lucide="vote"></i>Primaire</button>
                        <button class="btn btn-secondaire">Secondaire</button>
                        <button class="btn btn-tertiaire">Tertiaire</button>
                        <button class="btn btn-danger">Danger</button>
                        <button class="btn btn-discret"><i data-lucide="rotate-ccw"></i>Discret</button>
                        <button class="btn btn-primaire" disabled>Désactivé</button>
                    </div>
                    <div style="display:flex;gap:8px;flex-wrap:wrap">
                        <span class="badge badge--primaire">Ouvert</span><span class="badge badge--succes">Clôturé</span><span class="badge badge--attente">À départager</span>
                        <span class="badge badge--erreur">Annulé</span><span class="badge badge--info">Programmé</span><span class="badge badge--accent">Lauréate</span><span class="badge">Désactivée</span>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                        <div><label class="libelle">Champ</label><input class="champ" placeholder="Saisie"></div>
                        <div><label class="libelle">Lecture seule</label><input class="champ" readonly value="Calculé par le système"></div>
                    </div>
                </div>
            </section>
            <section class="carte">
                <div class="carte__entete"><h2>Édition · paramétrage d’une phase et de ses périmètres</h2></div>
                <div class="carte__corps">
                    <fieldset class="organisation-phase">
                        <legend class="libelle">Phase 2</legend>
                        <label class="case case--interrupteur"><input type="checkbox" role="switch" checked> Comité d’organisation</label>
                        <div class="formulaire__groupe"><label class="libelle" for="exemple-phase">Libellé</label><input id="exemple-phase" class="champ" value="Phase 2 · demi-finales"></div>
                        <div class="tableau-defilant">
                            <table class="tableau">
                                <thead><tr><th>Périmètre</th><th>Scrutins</th><th>Candidates</th><th class="nombre">Élues par scrutin</th></tr></thead>
                                <tbody>
                                    <tr><td>Pôle structures</td><td><select class="champ"><option>Un seul scrutin</option></select></td><td><select class="champ"><option>Nomination par le comité</option></select></td><td class="nombre"><input type="number" class="champ champ--court" value="3"></td></tr>
                                </tbody>
                            </table>
                        </div>
                    </fieldset>
                    <ul class="colleges-phase" style="margin-top:16px"><li><i data-lucide="users-round"></i>Pôle structures · Un seul scrutin · Nomination par le comité · 3 élue(s) par scrutin</li></ul>
                </div>
            </section>
            <section class="carte">
                <div class="carte__entete"><h2>Espace électeur · carte du scrutin (x-layouts.electeur)</h2></div>
                <div class="carte__corps">
                    <div class="scrutin-carte scrutin-carte--ouvert">
                        <div class="scrutin-carte__haut">
                            <div><div class="surtitre">Mon scrutin · Phase 1 · primaires</div><h2>Scrutin départemental · Atlantique</h2></div>
                            <span class="badge badge--succes">Ouvert</span>
                        </div>
                        <div class="scrutin-carte__infos"><span><i data-lucide="clock"></i>Clôture le 28/11/2026 à 18:00</span><span><i data-lucide="users-round"></i>38 candidate(s)</span></div>
                        <a href="#" class="btn btn-primaire btn--lg"><i data-lucide="vote"></i>Voter maintenant</a>
                    </div>
                </div>
            </section>
            <section class="carte">
                <div class="carte__entete"><h2>Page de vote · en-tête et étapes (x-layouts.vote)</h2></div>
                <header class="isoloir__entete">
                    <div class="isoloir__marque">
                        <img src="{{ asset('images/embleme-tresor.png') }}" alt="Trésor public">
                        <div><strong>Femme modèle du Trésor</strong><small>Phase 1 · Scrutin départemental · Atlantique</small></div>
                    </div>
                    <div class="etapes isoloir__etapes">
                        <div class="etape est-faite"><span class="etape__puce"><i data-lucide="check"></i></span><span class="etape__texte">Choisir<small>votre candidate</small></span></div>
                        <span class="etape__trait"></span>
                        <div class="etape est-en-cours"><span class="etape__puce">2</span><span class="etape__texte">Confirmer<small>votre vote</small></span></div>
                    </div>
                    <div class="isoloir__droite"><a href="#" class="btn btn-discret isoloir__quitter"><i data-lucide="x"></i><span>Quitter</span></a></div>
                </header>
                <div class="bande-tricolore"></div>
            </section>
        </div>

        <section class="carte">
            <div class="carte__entete"><h2>Fiche d’une personne</h2></div>
            <div class="carte__corps" style="display:grid;gap:20px">
                <div class="titre-page__identite">
                    <span class="avatar avatar--grand avatar--sans-anneau" aria-hidden="true">AC</span>
                    <div>
                        <h1 style="font-size:24px;line-height:32px;font-weight:700">AHOUANSOU Clarisse</h1>
                        <div class="titre-page__surtitre"><span class="etiquette"><i data-lucide="id-card"></i>104801</span><span class="etiquette">Féminin</span><span class="badge badge--succes">En service</span></div>
                    </div>
                </div>
                <dl class="recapitulatif__liste">
                    <div><dt>NPI</dt><dd>1173956900</dd></div>
                    <div><dt>Fonction</dt><dd class="est-vide">Non renseignée</dd></div>
                </dl>
            </div>
        </section>

        <section class="carte">
            <div class="carte__entete"><h2>Donut (x-donut)</h2></div>
            <div class="carte__corps grille-trois">
                <div class="donut-bloc">
                    <x-donut :segments="[['libelle' => 'Votants', 'valeur' => 2391, 'couleur' => 1], ['libelle' => 'Pas encore votés', 'valeur' => 2421, 'couleur' => 0]]" valeur="49,7 %" legende="votants" />
                    <ul class="legende-donut"><li><i class="pastille-graphe pastille-graphe--1"></i>Votants<b>2 391</b></li><li><i class="pastille-graphe"></i>Pas encore votés<b>2 421</b></li></ul>
                </div>
                <div class="donut-bloc">
                    <x-donut :segments="[['libelle' => 'Ouverts', 'valeur' => 9, 'couleur' => 1], ['libelle' => 'À venir', 'valeur' => 3, 'couleur' => 3], ['libelle' => 'Clos', 'valeur' => 8, 'couleur' => 5], ['libelle' => 'Résultats publiés', 'valeur' => 6, 'couleur' => 2]]" valeur="26" legende="scrutins" />
                    <ul class="legende-donut"><li><i class="pastille-graphe pastille-graphe--1"></i>Ouverts<b>9</b></li><li><i class="pastille-graphe pastille-graphe--3"></i>À venir<b>3</b></li><li><i class="pastille-graphe pastille-graphe--5"></i>Clos<b>8</b></li><li><i class="pastille-graphe pastille-graphe--2"></i>Résultats publiés<b>6</b></li></ul>
                </div>
                <div class="donut-bloc">
                    <x-donut :segments="[['libelle' => 'Actives', 'valeur' => 312, 'couleur' => 2], ['libelle' => 'Désistements', 'valeur' => 4, 'couleur' => 6]]" valeur="312" petit />
                </div>
            </div>
        </section>

        <section class="carte">
            <div class="carte__entete"><h2>Parcours des phases (.frise-phases) et tâches d’une phase (.taches-phase)</h2></div>
            <nav class="frise-phases" aria-label="Phases de l’édition">
                <ol>
                    <li class="frise-phases__etape est-faite"><a href="#"><span class="frise-phases__puce"><i data-lucide="check"></i></span><span class="frise-phases__texte"><span class="frise-phases__numero">Étape 1 sur 3</span><strong>Phase 1 · primaires</strong><small>Du 29/11/2026 au 04/12/2026</small><span class="badge badge--accent">Clôturée</span></span></a></li>
                    <li class="frise-phases__etape est-choisie"><a href="#" aria-current="step"><span class="frise-phases__puce">2</span><span class="frise-phases__texte"><span class="frise-phases__numero">Étape 2 sur 3</span><strong>Phase 2 · demi-finales</strong><small>Du 05/12/2026 au 14/12/2026</small><span class="badge badge--info">Scrutins générés</span></span></a></li>
                    <li class="frise-phases__etape"><a href="#"><span class="frise-phases__puce">3</span><span class="frise-phases__texte"><span class="frise-phases__numero">Étape 3 sur 3</span><strong>Phase 3 · finale</strong><small>Dates à planifier</small><span class="badge">À planifier</span></span></a></li>
                </ol>
            </nav>
            <ol class="taches-phase">
                <li class="taches-phase__tache est-faite"><span class="taches-phase__puce"><i data-lucide="calendar-check"></i></span><div><div class="taches-phase__titre">Planifier la phase</div><div class="taches-phase__detail">Du samedi 05/12/2026 au lundi 14/12/2026 (10 jours).</div></div><div class="taches-phase__action"><button class="btn btn-tertiaire btn--sm"><i data-lucide="pencil"></i>Modifier les dates</button></div></li>
                <li class="taches-phase__tache est-faite"><span class="taches-phase__puce"><i data-lucide="wand-sparkles"></i></span><div><div class="taches-phase__titre">Générer les scrutins</div><div class="taches-phase__detail">2 scrutin(s) générés, un par zone de vote.</div></div><div class="taches-phase__action"><span class="badge badge--succes">Fait</span></div></li>
                <li class="taches-phase__tache est-courante"><span class="taches-phase__puce"><i data-lucide="lock"></i></span><div><div class="taches-phase__titre">Arrêter les listes électorales</div><div class="taches-phase__detail">2 scrutin(s) aux listes encore modifiables.</div></div><div class="taches-phase__action"><button class="btn btn-primaire"><i data-lucide="lock"></i>Arrêter les listes</button></div></li>
                <li class="taches-phase__tache est-a-venir"><span class="taches-phase__puce"><i data-lucide="play"></i></span><div><div class="taches-phase__titre">Ouvrir la phase</div><div class="taches-phase__detail">Chaque scrutin s’ouvrira ensuite automatiquement à sa date-heure d’ouverture.</div></div><div class="taches-phase__action"><button class="btn btn-secondaire"><i data-lucide="play"></i>Ouvrir la phase</button></div></li>
            </ol>
        </section>

        <section class="carte etape-courante">
            <div class="carte__entete"><span class="frise-phases__numero">Étape 2 sur 5</span><h2>Planifier le scrutin (.etape-courante)</h2></div>
            <div class="etape-courante__corps">
                <span class="pastille-icone"><i data-lucide="calendar-clock"></i></span>
                <div class="etape-courante__texte"><strong>Quand le vote s’ouvre-t-il et se ferme-t-il ?</strong><p>Le scrutin s’ouvrira et se fermera seul à ces heures, une fois la phase ouverte.</p></div>
                <div class="etape-courante__actions"><button class="btn btn-primaire"><i data-lucide="calendar-clock"></i>Planifier le scrutin</button></div>
            </div>
            <div class="etape-courante__rappel"><span><i data-lucide="circle-check"></i>Listes arrêtées : 412 inscrit(s), 38 candidate(s) active(s)</span><button type="button" class="lien">Rouvrir les listes</button></div>
        </section>
    </div>
</x-layouts.maquette>
