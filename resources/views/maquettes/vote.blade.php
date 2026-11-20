@php
    $candidates = [
        ['ADJOVI Mireille', 'TC Abomey-Calavi', 'AM'], ['AGOSSOU Carine', 'TD Atlantique', 'AC'], ['AHOUANDJINOU Sandrine', 'TC Ouidah', 'AS'],
        ['DOSSOU Prisca', 'TC Allada', 'DP'], ['GBAGUIDI Florence', 'TD Atlantique', 'GF'], ['HOUNKPATIN Clarisse', 'TC Tori-Bossito', 'HC'],
        ['KOUTON Edwige', 'TC Zè', 'KE'], ['SOGLO Bénédicte', 'TC Kpomassè', 'SB'],
    ];
@endphp
<x-layouts.vote titre="Voter" contexte="Phase 1 · élections primaires · Scrutin départemental · Atlantique" :etape="1">
    <div class="titre-page">
        <div>
            <h1>Choisissez votre candidate</h1>
            <p>Scrutin départemental · Atlantique</p>
        </div>
        <div class="titre-page__actions">
            <span class="badge badge--primaire">Ouvert</span>
            <span class="etiquette"><i data-lucide="clock"></i>Clôture le 28 nov. à 18 h 00</span>
        </div>
    </div>

    <section class="carte" style="margin-bottom:4px">
        <div class="barre-filtres">
            <div class="champ-icone"><i data-lucide="search"></i><input class="champ" placeholder="Rechercher une candidate par nom"></div>
            <select class="champ"><option>Toutes les structures</option><option>TD Atlantique</option><option>TC Abomey-Calavi</option><option>TC Ouidah</option></select>
            <span style="margin-left:auto;font-size:13px;color:var(--tr-texte-2)"><b class="chiffres" style="color:var(--tr-texte)">38</b> candidates</span>
        </div>
        <div class="carte__corps">
            <form class="grille-candidates" id="bulletin">
                @foreach ($candidates as $i => [$nom, $structure, $ini])
                    <label class="carte-candidate">
                        <input type="radio" name="candidate" value="{{ $i }}" @checked($i === 2)>
                        <span class="carte-candidate__coche"><i data-lucide="check"></i></span>
                        <span class="avatar avatar--grand avatar--sans-anneau">{{ $ini }}</span>
                        <span><span class="carte-candidate__nom">{{ $nom }}</span><br><span class="carte-candidate__structure">{{ $structure }}</span></span>
                    </label>
                @endforeach
            </form>
        </div>
        <div class="pagination">1 à 8 sur 38 candidates<div class="pagination__pages"><button class="btn btn-secondaire btn--sm">Précédent</button><button class="btn btn-secondaire btn--sm">Suivant</button></div></div>
    </section>

    <div class="barre-vote">
        <span class="avatar avatar--sans-anneau">AS</span>
        <div><div class="surtitre">Votre choix</div><b>AHOUANDJINOU Sandrine</b> · TC Ouidah</div>
        <div class="barre-vote__actions">
            <a href="#" class="btn btn-discret">Plus tard</a>
            <button class="btn btn-primaire btn--lg" data-ouvrir="confirmation"><i data-lucide="vote"></i>Continuer vers la confirmation</button>
        </div>
    </div>

    <x-slot:modales>
        <div class="voile" id="confirmation" hidden>
            <div class="modale" role="dialog" aria-modal="true" aria-labelledby="titre-confirmation">
                <div class="modale__entete">
                    <span class="pastille-icone"><i data-lucide="vote"></i></span>
                    <div><h2 id="titre-confirmation">Confirmer votre vote</h2><p>Scrutin départemental · Atlantique · phase 1</p></div>
                </div>
                <div class="modale__corps">
                    <div class="carte" style="display:flex;align-items:center;gap:12px;padding:14px 16px;box-shadow:none">
                        <span class="avatar avatar--sans-anneau">AS</span>
                        <div><b>AHOUANDJINOU Sandrine</b><div style="font-size:12px;color:var(--tr-texte-3)">TC Ouidah</div></div>
                    </div>
                    <p style="margin-top:14px;color:var(--tr-texte-2)">Votre vote sera enregistré de façon anonyme et <b style="color:var(--tr-texte)">ne pourra plus être modifié</b>. Vous recevrez un accusé de vote sans mention de votre choix.</p>
                </div>
                <div class="modale__pied">
                    <button class="btn btn-secondaire" data-fermer>Revenir au bulletin</button>
                    <button class="btn btn-primaire" data-ouvrir="accuse" data-fermer><i data-lucide="check"></i>Confirmer mon vote</button>
                </div>
            </div>
        </div>
        <div class="voile" id="accuse" hidden>
            <div class="modale" role="dialog" aria-modal="true">
                <div class="modale__entete">
                    <span class="pastille-icone pastille-icone--succes"><i data-lucide="circle-check"></i></span>
                    <div><h2>Votre vote est enregistré</h2><p>Merci pour votre participation.</p></div>
                </div>
                <div class="modale__corps">
                    <div class="tableau-defilant"><table class="tableau"><tbody>
                        <tr><td>Accusé n°</td><td class="nombre reference">AV-2026-PH1-0029147</td></tr>
                        <tr><td>Scrutin</td><td class="nombre">Départemental · Atlantique</td></tr>
                        <tr><td>Horodatage</td><td class="nombre">19 nov. 2026 · 10 h</td></tr>
                    </tbody></table></div>
                    <p class="aide">L'accusé ne mentionne pas la candidate choisie, conformément au secret du vote.</p>
                </div>
                <div class="modale__pied"><button class="btn btn-secondaire"><i data-lucide="printer"></i>Imprimer l'accusé</button><button class="btn btn-primaire" data-fermer>Terminer</button></div>
            </div>
        </div>
    </x-slot:modales>
</x-layouts.vote>
