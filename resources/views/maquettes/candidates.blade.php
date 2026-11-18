@php
    $liste = [
        ['123087', 'ADJOVI Mireille', 'Service de la comptabilité', 'Active', null],
        ['118442', 'AKPLOGAN Nadège', 'Trésorerie spéciale des correspondants', 'Active', null],
        ['120915', 'BIO SIKA Rachidatou', 'Service de la caisse', 'Désactivée', '18 nov. · à sa demande (point focal)'],
        ['115306', 'DJOSSOU Ghislaine', 'Service des dépôts', 'Active', null],
        ['121774', 'FAGLA Hortense', 'Secrétariat', 'Désactivée', '17 nov. · désistement en ligne'],
        ['119660', 'TCHIBOZO Laure', 'Service de la comptabilité', 'Active', null],
    ];
@endphp
<x-layouts.maquette titre="Candidates" :ariane="['Électeurs et candidates', 'Candidates']" profil="comite" actif="candidates">
    <div class="titre-page">
        <div>
            <div class="surtitre">Phase 1 · scrutin PH1-STR-TGE</div>
            <h1>Candidates d'office · Trésorerie générale de l'État</h1>
            <p>Agents femmes en service dans la structure à la date de gel (15 nov. 2026). Désactivation possible jusqu'à l'ouverture du scrutin.</p>
        </div>
        <div class="titre-page__actions">
            <button class="btn btn-secondaire"><i data-lucide="download"></i>Exporter la liste</button>
        </div>
    </div>

    <div class="alerte alerte--attente" style="margin-bottom:20px"><i data-lucide="hourglass"></i>
        <div><strong>Ouverture du scrutin dans 2 jours.</strong> Après l'ouverture, les candidates ne peuvent plus être désactivées ni réactivées.</div>
    </div>

    <section class="carte">
        <div class="onglets">
            <a href="#" class="onglet est-actif">Toutes <span class="compteur">26</span></a>
            <a href="#" class="onglet">Actives <span class="compteur">24</span></a>
            <a href="#" class="onglet">Désactivées <span class="compteur">2</span></a>
        </div>
        <div class="barre-filtres">
            <div class="champ-icone"><i data-lucide="search"></i><input class="champ" placeholder="Nom ou matricule"></div>
            <select class="champ"><option>Tous les services</option><option>Service de la comptabilité</option><option>Service de la caisse</option></select>
            <button class="btn btn-secondaire"><i data-lucide="sliders-horizontal"></i>Filtres avancés</button>
        </div>
        <div class="tableau-defilant">
            <table class="tableau">
                <thead><tr><th>Matricule</th><th>Candidate</th><th>Service</th><th>Statut</th><th class="actions">Actions</th></tr></thead>
                <tbody>
                @foreach ($liste as [$mat, $nom, $service, $statut, $trace])
                    <tr>
                        <td class="chiffres">{{ $mat }}</td>
                        <td><div class="cellule-personne"><span class="avatar avatar--sans-anneau">{{ collect(explode(' ', $nom))->map(fn ($m) => mb_substr($m, 0, 1))->take(2)->implode('') }}</span><div>{{ $nom }}@if ($trace)<small>{{ $trace }}</small>@endif</div></div></td>
                        <td>{{ $service }}</td>
                        <td><span class="badge {{ $statut === 'Active' ? 'badge--succes' : '' }}">{{ $statut }}</span></td>
                        <td class="actions">
                            @if ($statut === 'Active')
                                <button class="btn btn-danger-contour btn--sm" data-ouvrir="desactiver"><i data-lucide="user-x"></i>Désactiver</button>
                            @else
                                <button class="btn btn-tertiaire btn--sm"><i data-lucide="user-check"></i>Réactiver</button>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="pagination">1 à 6 sur 26 candidates<div class="pagination__pages"><button class="btn btn-secondaire btn--sm">Précédent</button><button class="btn btn-secondaire btn--sm">Suivant</button></div></div>
    </section>

    <x-slot:modales>
        <div class="voile" id="desactiver" hidden>
            <div class="modale" role="dialog" aria-modal="true" aria-labelledby="titre-desactiver">
                <div class="modale__entete">
                    <span class="pastille-icone pastille-icone--danger"><i data-lucide="user-x"></i></span>
                    <div><h2 id="titre-desactiver">Désactiver ADJOVI Mireille ?</h2><p>Matricule 123087 · scrutin PH1-STR-TGE</p></div>
                </div>
                <div class="modale__corps">
                    <p style="color:var(--tr-texte-2);margin-bottom:14px">La candidate ne figurera plus sur le bulletin et ne pourra recevoir aucune voix. Elle reste électrice. Vous pourrez la réactiver jusqu'à l'ouverture du scrutin.</p>
                    <label class="libelle" for="motif">Motif (facultatif)</label>
                    <textarea id="motif" class="champ" placeholder="Ex. : ne souhaite pas concourir, demande écrite du 18 novembre"></textarea>
                    <p class="aide">La désactivation est enregistrée dans le journal d'audit (date, auteur, motif).</p>
                </div>
                <div class="modale__pied">
                    <button class="btn btn-secondaire" data-fermer>Annuler</button>
                    <button class="btn btn-danger" data-fermer><i data-lucide="user-x"></i>Désactiver la candidate</button>
                </div>
            </div>
        </div>
    </x-slot:modales>
</x-layouts.maquette>
