@use('App\Enums\Editions\StatutEdition')
@use('App\Enums\Editions\StatutPhase')
<x-layouts.app :titre="'Édition '.$edition->annee" :ariane="['Pilotage', 'Éditions', (string) $edition->annee]" actif="editions">
    <div class="titre-page">
        <div>
            <div class="surtitre">Édition {{ $edition->annee }} · {{ $edition->statut->libelle() }}</div>
            <h1>{{ $edition->libelle }}</h1>
            <p>Du {{ $edition->date_debut->format('d/m/Y') }} au {{ $edition->date_fin->format('d/m/Y') }} · listes gelées au {{ $edition->date_gel->format('d/m/Y') }}</p>
        </div>
        <div class="titre-page__actions">
            @if ($edition->estModifiable())
                <a href="{{ route('editions.edit', $edition) }}" class="btn btn-secondaire"><i data-lucide="settings"></i>Paramétrer</a>
                <button type="button" class="btn btn-primaire" data-ouvrir="modale-lancer"><i data-lucide="rocket"></i>Lancer l’édition</button>
            @endif
        </div>
    </div>

    <div class="grille-tuiles espace-bloc">
        <div class="tuile">
            <div class="tuile__haut"><span class="surtitre">Vote blanc</span><span class="tuile__icone"><i data-lucide="file"></i></span></div>
            <div class="tuile__valeur">{{ $edition->vote_blanc_autorise ? 'Autorisé' : 'Non' }}</div>
        </div>
        <div class="tuile">
            <div class="tuile__haut"><span class="surtitre">Vote pour soi-même</span><span class="tuile__icone"><i data-lucide="user-check"></i></span></div>
            <div class="tuile__valeur">{{ $edition->vote_pour_soi_autorise ? 'Autorisé' : 'Non' }}</div>
        </div>
        <div class="tuile">
            <div class="tuile__haut"><span class="surtitre">Quorum</span><span class="tuile__icone"><i data-lucide="percent"></i></span></div>
            <div class="tuile__valeur">{{ $edition->quorum === null ? 'Aucun' : number_format((float) $edition->quorum, 0, ',', ' ').' %' }}</div>
        </div>
        <div class="tuile">
            <div class="tuile__haut"><span class="surtitre">Rééligibilité</span><span class="tuile__icone"><i data-lucide="crown"></i></span></div>
            <div class="tuile__valeur">{{ $edition->delai_reeligibilite }} an(s)</div>
            <div class="tuile__detail">après l’élection d’une femme modèle</div>
        </div>
    </div>

    @foreach ($edition->phases as $phase)
        <section class="carte espace-bloc" id="phase-{{ $phase->numero }}">
            <div class="carte__entete">
                <h2>{{ $phase->libelle }}</h2>
                <span class="badge {{ $phase->statut->badge() }}">{{ $phase->statut->libelle() }}</span>
            </div>
            <div class="carte__corps">
                @if ($phase->statut === StatutPhase::Planifiee && $edition->statut !== StatutEdition::Cloturee)
                    <form method="POST" action="{{ route('phases.update', $phase) }}" class="formulaire-ligne">
                        @csrf
                        @method('PUT')
                        <div class="formulaire__groupe">
                            <label class="libelle" for="debut-{{ $phase->id }}">Début</label>
                            <input id="debut-{{ $phase->id }}" name="date_debut" type="date" class="champ" value="{{ $phase->date_debut?->format('Y-m-d') }}" required>
                        </div>
                        <div class="formulaire__groupe">
                            <label class="libelle" for="fin-{{ $phase->id }}">Fin</label>
                            <input id="fin-{{ $phase->id }}" name="date_fin" type="date" class="champ" value="{{ $phase->date_fin?->format('Y-m-d') }}" required>
                        </div>
                        <button type="submit" class="btn btn-secondaire"><i data-lucide="calendar-check"></i>Planifier la phase</button>
                    </form>
                @else
                    <p class="espace-bloc">Du {{ $phase->date_debut?->format('d/m/Y') }} au {{ $phase->date_fin?->format('d/m/Y') }}</p>
                @endif

                <ul class="colleges-phase" aria-label="Collèges de la phase">
                    @foreach ($phase->colleges as $college)
                        <li><i data-lucide="users-round"></i>{{ $college->resume() }}</li>
                    @endforeach
                </ul>

                <x-editions.scrutins-de-la-phase :phase="$phase" />

                @if ($edition->statut === StatutEdition::EnCours)
                    <div class="formulaire__actions">
                        @if ($phase->statut === StatutPhase::Planifiee)
                            <button type="button" class="btn btn-primaire" data-ouvrir="modale-ouvrir-{{ $phase->id }}"><i data-lucide="play"></i>Ouvrir la phase</button>
                        @elseif ($phase->statut === StatutPhase::Ouverte)
                            <button type="button" class="btn btn-primaire" data-ouvrir="modale-cloturer-{{ $phase->id }}"><i data-lucide="flag"></i>Clôturer la phase</button>
                        @endif
                    </div>
                @endif
            </div>
        </section>
    @endforeach

    <x-slot:modales>
        @if ($edition->estModifiable())
            <x-modale-confirmation id="modale-lancer" :titre="'Lancer l’édition '.$edition->annee.' ?'" :action="route('editions.lancer', $edition)" bouton="Lancer l’édition" icone="rocket">
                L’édition passera en cours et ne pourra plus être modifiée. Toutes les phases doivent être planifiées, et aucune autre édition ne doit être en cours.
            </x-modale-confirmation>
        @endif
        @foreach ($edition->phases as $phase)
            @if ($phase->statut === StatutPhase::Planifiee)
                <x-modale-confirmation :id="'modale-ouvrir-'.$phase->id" :titre="'Ouvrir la '.$phase->libelle.' ?'" :action="route('phases.ouvrir', $phase)" bouton="Ouvrir la phase" icone="play">
                    Chaque scrutin s’ouvrira ensuite automatiquement à sa date-heure d’ouverture. La phase précédente doit être clôturée.
                </x-modale-confirmation>
            @elseif ($phase->statut === StatutPhase::Ouverte)
                <x-modale-confirmation :id="'modale-cloturer-'.$phase->id" :titre="'Clôturer la '.$phase->libelle.' ?'" :action="route('phases.cloturer', $phase)" bouton="Clôturer la phase" icone="flag">
                    Tous les résultats doivent être validés et publiés. Les lauréates seront alors reprises dans la phase suivante.
                </x-modale-confirmation>
            @endif
        @endforeach
    </x-slot:modales>
</x-layouts.app>
