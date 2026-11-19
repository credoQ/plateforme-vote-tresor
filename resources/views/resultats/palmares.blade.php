<x-layouts.app titre="Résultats" :ariane="['Mon espace', 'Résultats']" actif="palmares">
    <div class="titre-page">
        <div>
            <div class="surtitre">{{ $edition?->libelle ?? 'Résultats' }}</div>
            <h1>Palmarès</h1>
        </div>
    </div>

    @if ($palmares === null)
        <div class="carte"><div class="carte__corps"><div class="etat-vide"><i data-lucide="trophy"></i><p>Aucune édition n’est encore organisée.</p></div></div></div>
    @else
        @if ($femme = $palmares['femme_modele'])
            <div class="tuile tuile--heros espace-bloc">
                <div class="tuile__haut"><span class="surtitre">Femme modèle du Trésor {{ $edition->annee }}</span><span class="tuile__icone"><i data-lucide="crown"></i></span></div>
                <div class="tuile__valeur">{{ $femme->agent->nomComplet() }}</div>
                <div class="tuile__detail">Lauréate de la finale · {{ $femme->nb_voix }} voix</div>
            </div>
        @endif

        @foreach ($palmares['phases'] as $phase)
            <section class="carte espace-bloc">
                <div class="carte__entete"><h2>{{ $phase['phase'] }}</h2></div>
                <div class="tableau-defilant">
                    <table class="tableau tableau--fiches">
                        <thead><tr><th>Scrutin</th><th>Lauréate</th><th>Matricule</th><th class="nombre">Voix</th></tr></thead>
                        <tbody>
                            @forelse ($phase['laureates'] as $laureate)
                                <tr>
                                    <td>{{ $laureate['scrutin'] }}</td>
                                    <td><span class="badge badge--accent">Lauréate</span> {{ $laureate['agent'] }}</td>
                                    <td>{{ $laureate['matricule'] }}</td>
                                    <td class="nombre">{{ $laureate['voix'] }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4"><div class="etat-vide"><p>Aucun résultat publié pour cette phase.</p></div></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        @endforeach
    @endif
</x-layouts.app>
