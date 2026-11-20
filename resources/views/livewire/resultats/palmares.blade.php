{{-- Palmarès : filtres appliqués pendant la saisie. La liste des phases dépend de l'édition choisie : sa clé
     (wire:key) change avec l'année, pour que Livewire la reconstruise malgré wire:ignore. --}}
<div>
    <div class="titre-page">
        <div>
            <div class="surtitre">{{ $edition?->libelle ?? 'Résultats' }}</div>
            <h1>Palmarès</h1>
        </div>
    </div>

    @if ($palmares === null)
        <div class="carte"><div class="carte__corps"><div class="etat-vide"><i data-lucide="trophy"></i><p>Aucune édition n’est encore organisée.</p></div></div></div>
    @else
        <div class="carte espace-bloc">
            <div class="barre-filtres">
                <x-filtre libelle="Recherche" pour="filtre-recherche" large>
                    <div class="champ-icone"><i data-lucide="search"></i><input id="filtre-recherche" type="search" class="champ" wire:model.live.debounce.400ms="recherche" placeholder="Lauréate, matricule ou scrutin"></div>
                </x-filtre>
                <x-filtre libelle="Édition" pour="filtre-annee">
                    <x-select-recherche compact wire:ignore>
                        <select id="filtre-annee" class="champ" wire:model.live="annee">
                            @foreach ($annees as $choix)
                                <option value="{{ $choix }}" @selected($edition->annee === $choix)>{{ $choix }}</option>
                            @endforeach
                        </select>
                    </x-select-recherche>
                </x-filtre>
                <x-filtre libelle="Phase" pour="filtre-phase">
                    <x-select-recherche compact wire:ignore wire:key="filtre-phase-{{ $edition->annee }}">
                        <select id="filtre-phase" class="champ" wire:model.live="phase">
                            <option value="">Toutes les phases</option>
                            @foreach ($phases as $choix)
                                <option value="{{ $choix->numero }}" @selected($phase === (string) $choix->numero)>{{ $choix->libelle }}</option>
                            @endforeach
                        </select>
                    </x-select-recherche>
                </x-filtre>
                <x-fin-filtres :total="$totalLaureates" libelle="lauréate(s)" :actifs="$filtresActifs" :reinitialiser="route('palmares', ['annee' => $edition->annee])" />
            </div>
        </div>

        <div wire:loading.class="est-en-chargement">
            @if ($palmares['femmes_modeles']->isNotEmpty())
                <div class="grille-tuiles espace-bloc">
                    @foreach ($palmares['femmes_modeles'] as $femme)
                        <div class="tuile tuile--heros" wire:key="femme-modele-{{ $femme->id }}">
                            <div class="tuile__haut"><span class="surtitre">Femme modèle du Trésor {{ $edition->annee }}</span><span class="tuile__icone"><i data-lucide="crown"></i></span></div>
                            <div class="tuile__valeur">{{ $femme->agent->nomComplet() }}</div>
                            <div class="tuile__detail">{{ $femme->scrutin->libelle }} · {{ $femme->nb_voix }} voix</div>
                        </div>
                    @endforeach
                </div>
            @endif

            @foreach ($palmares['phases'] as $phaseDuPalmares)
                <section class="carte espace-bloc" wire:key="phase-{{ $edition->annee }}-{{ $phaseDuPalmares['numero'] }}">
                    <div class="carte__entete"><h2>{{ $phaseDuPalmares['phase'] }}</h2></div>
                    <div class="tableau-defilant">
                        <table class="tableau tableau--fiches">
                            <thead><tr><th>Scrutin</th><th>Lauréate</th><th>Matricule</th><th class="nombre">Voix</th></tr></thead>
                            <tbody>
                                @forelse ($phaseDuPalmares['laureates'] as $laureate)
                                    <tr>
                                        <td>{{ $laureate['scrutin'] }}</td>
                                        <td><span class="badge badge--accent">Lauréate</span> {{ $laureate['agent'] }}</td>
                                        <td>{{ $laureate['matricule'] }}</td>
                                        <td class="nombre">{{ $laureate['voix'] }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4"><div class="etat-vide"><p>{{ $recherche !== '' ? 'Aucune lauréate ne correspond à la recherche.' : 'Aucun résultat publié pour cette phase.' }}</p></div></td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
            @endforeach
        </div>
    @endif
</div>
