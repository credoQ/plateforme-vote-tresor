@use('App\Enums\Scrutins\StatutScrutin')
<x-layouts.app titre="Tableau de bord" :ariane="['Suivi', 'Tableau de bord']" actif="tableau-de-bord">
    <div class="titre-page">
        <div>
            <div class="surtitre">{{ $edition?->libelle ?? 'Aucune édition' }}</div>
            <h1>Participation{{ $phase ? ' · '.$phase->libelle : '' }}</h1>
            @if ($restreint)<p>Données limitées à votre structure.</p>@endif
        </div>
    </div>

    @if ($participation === null)
        <div class="carte"><div class="carte__corps"><div class="etat-vide"><i data-lucide="calendar-x"></i><p>Aucune édition n’est encore créée.</p></div></div></div>
    @else
        <div class="grille-tuiles espace-bloc">
            <div class="tuile tuile--heros">
                <div class="tuile__haut"><span class="surtitre">Taux de participation</span><span class="tuile__icone"><i data-lucide="percent"></i></span></div>
                <div class="tuile__valeur">{{ number_format($global['taux'], 1, ',', ' ') }} %</div>
                <div class="tuile__detail">{{ number_format($global['votants'], 0, ',', ' ') }} votants sur {{ number_format($global['inscrits'], 0, ',', ' ') }} inscrits</div>
            </div>
            <div class="tuile">
                <div class="tuile__haut"><span class="surtitre">Scrutins ouverts</span><span class="tuile__icone"><i data-lucide="clipboard-list"></i></span></div>
                <div class="tuile__valeur">{{ $indicateurs['ouverts'] }} <small>/ {{ $scrutins->count() }}</small></div>
                <div class="tuile__detail">{{ $indicateurs['termines'] }} terminé(s)</div>
            </div>
            <div class="tuile">
                <div class="tuile__haut"><span class="surtitre">Candidates actives</span><span class="tuile__icone"><i data-lucide="users-round"></i></span></div>
                <div class="tuile__valeur">{{ number_format($indicateurs['candidates'], 0, ',', ' ') }}</div>
                <div class="tuile__detail">sur {{ $scrutins->count() }} scrutin(s)</div>
            </div>
        </div>

        <section class="carte espace-bloc">
            <div class="carte__entete"><h2>Suivi des scrutins</h2></div>
            <div class="tableau-defilant">
                <table class="tableau tableau--fiches">
                    <thead><tr><th>Scrutin</th><th>Pôle</th><th class="nombre">Inscrits</th><th class="nombre">Votants</th><th>Participation</th><th>Statut</th><th class="actions">Exports</th></tr></thead>
                    <tbody>
                        @forelse ($scrutins as $ligne)
                            <tr>
                                <td><a class="lien" href="{{ route('scrutins.show', $ligne['scrutin']) }}">{{ $ligne['scrutin']->libelle }}</a><div class="aide">{{ $ligne['scrutin']->code }} · {{ $ligne['scrutin']->candidates_actives }} candidate(s)</div></td>
                                <td>{{ $ligne['scrutin']->poleElectoral?->code->libelle() ?? 'Toute la DGTCP' }}</td>
                                <td class="nombre">{{ number_format($ligne['inscrits'], 0, ',', ' ') }}</td>
                                <td class="nombre">{{ number_format($ligne['votants'], 0, ',', ' ') }}</td>
                                <td><div class="cellule-progression"><div class="progression"><span style="width:{{ $ligne['taux'] }}%"></span></div><b>{{ number_format($ligne['taux'], 1, ',', ' ') }} %</b></div></td>
                                <td><span class="badge {{ $ligne['scrutin']->statut->badge() }}">{{ $ligne['scrutin']->statut->libelle() }}</span></td>
                                <td class="actions">
                                    <a href="{{ route('exports.liste-electorale', [$ligne['scrutin'], 'format' => 'xlsx']) }}" class="btn btn-discret btn--icone" title="Exporter la liste électorale (Excel)"><i data-lucide="download"></i></a>
                                    @if ($ligne['scrutin']->statut === StatutScrutin::ResultatsPublies)<a href="{{ route('exports.resultats', [$ligne['scrutin'], 'format' => 'xlsx']) }}" class="btn btn-discret btn--icone" title="Exporter les résultats (Excel)"><i data-lucide="trophy"></i></a>@endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7"><div class="etat-vide"><i data-lucide="clipboard-list"></i><p>Les scrutins de cette phase ne sont pas encore générés.</p></div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <div class="grille-deux">
            @foreach ([['Par département', $participation['departements'], 'libelle'], ['Par structure', $participation['structures'], 'code']] as [$titre, $lignes, $cle])
                <section class="carte">
                    <div class="carte__entete"><h2>{{ $titre }}</h2></div>
                    <div class="tableau-defilant">
                        <table class="tableau tableau--fiches">
                            <thead><tr><th>{{ $cle === 'code' ? 'Structure' : 'Département' }}</th><th class="nombre">Inscrits</th><th>Participation</th></tr></thead>
                            <tbody>
                                @forelse ($lignes as $ligne)
                                    <tr>
                                        <td>{{ $ligne[$cle] }}@if ($cle === 'code')<div class="aide">{{ $ligne['libelle'] }}</div>@endif</td>
                                        <td class="nombre">{{ number_format($ligne['inscrits'], 0, ',', ' ') }}</td>
                                        <td><div class="cellule-progression"><div class="progression"><span style="width:{{ $ligne['taux'] }}%"></span></div><b>{{ number_format($ligne['taux'], 1, ',', ' ') }} %</b></div></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3"><div class="etat-vide"><p>Aucun inscrit.</p></div></td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
            @endforeach
        </div>
    @endif
</x-layouts.app>
