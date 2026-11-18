@php use App\Enums\Editions\StatutEdition; @endphp
<x-layouts.app titre="Éditions" :ariane="['Pilotage', 'Éditions']" actif="editions">
    <div class="titre-page">
        <div>
            <h1>Éditions</h1>
        </div>
        <div class="titre-page__actions">
            <a href="{{ route('editions.create') }}" class="btn btn-primaire"><i data-lucide="plus"></i>Créer une édition</a>
        </div>
    </div>

    <div class="carte">
        <div class="tableau-defilant">
            <table class="tableau">
                <thead><tr><th>Année</th><th>Libellé</th><th>Calendrier</th><th>Gel des listes</th><th class="nombre">Scrutins</th><th>Statut</th><th class="actions"></th></tr></thead>
                <tbody>
                    @forelse ($editions as $edition)
                        <tr>
                            <td class="reference">{{ $edition->annee }}</td>
                            <td>{{ $edition->libelle }}</td>
                            <td>du {{ $edition->date_debut->format('d/m/Y') }} au {{ $edition->date_fin->format('d/m/Y') }}</td>
                            <td>{{ $edition->date_gel->format('d/m/Y') }}</td>
                            <td class="nombre">{{ $edition->scrutins_count }}</td>
                            <td><span @class(['badge', 'badge--info' => $edition->statut === StatutEdition::EnPreparation, 'badge--succes' => $edition->statut === StatutEdition::EnCours, 'badge--accent' => $edition->statut === StatutEdition::Cloturee])>{{ $edition->statut->libelle() }}</span></td>
                            <td class="actions"><a href="{{ route('editions.show', $edition) }}" class="btn btn-tertiaire btn--sm"><i data-lucide="eye"></i>Ouvrir</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><div class="etat-vide"><i data-lucide="calendar-plus"></i><p>Aucune édition.</p></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.app>
