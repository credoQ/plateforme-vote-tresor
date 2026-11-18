@php
    use App\Enums\Scrutins\StatutScrutin;
    $fuseau = 'Africa/Porto-Novo';
    $scrutin = $inscription?->scrutin;
@endphp
<x-layouts.app titre="Voter" :ariane="['Mon espace', 'Voter']" actif="vote">
    <div class="titre-page">
        <div>
            <div class="surtitre">{{ $scrutin?->phase->libelle ?? 'Vote' }}</div>
            <h1>{{ $scrutin?->libelle ?? 'Aucun scrutin en cours' }}</h1>
        </div>
    </div>

    @error('candidate_id')
        <div class="alerte alerte--erreur espace-bloc" role="alert"><i data-lucide="circle-alert"></i><div>{{ $message }}</div></div>
    @enderror

    @if ($inscription === null)
        <div class="carte"><div class="carte__corps"><div class="etat-vide"><i data-lucide="calendar-clock"></i>
            <p>Vous n’êtes inscrit sur aucune liste électorale de la phase en cours. Si votre structure n’a pas de scrutin en phase 1, vous voterez aux phases 2 et 3.</p>
        </div></div></div>
    @elseif ($inscription->a_vote)
        <section class="carte accuse-vote">
            <div class="carte__entete">
                <span class="pastille-icone pastille-icone--succes"><i data-lucide="circle-check"></i></span>
                <div><h2>{{ session('vote_enregistre') ? 'Votre vote est enregistré' : 'Vous avez voté' }}</h2><p class="aide">Merci pour votre participation.</p></div>
            </div>
            <div class="carte__corps">
                <table class="tableau">
                    <tbody>
                        <tr><td>Accusé n°</td><td class="nombre reference">{{ $inscription->numero_accuse }}</td></tr>
                        <tr><td>Scrutin</td><td class="nombre">{{ $scrutin->libelle }}</td></tr>
                        <tr><td>Horodatage</td><td class="nombre">{{ $inscription->date_heure_vote->timezone($fuseau)->format('d/m/Y') }}, entre {{ $inscription->date_heure_vote->timezone($fuseau)->format('H') }} h et {{ $inscription->date_heure_vote->timezone($fuseau)->addHour()->format('H') }} h</td></tr>
                    </tbody>
                </table>
                <p class="aide">L’accusé ne mentionne pas la candidate choisie, conformément au secret du vote. L’heure est volontairement arrondie.</p>
                <div class="formulaire__actions">
                    <button type="button" class="btn btn-secondaire" data-imprimer><i data-lucide="printer"></i>Imprimer l’accusé</button>
                    <a href="{{ route('espace') }}" class="btn btn-primaire">Terminer</a>
                </div>
            </div>
        </section>
    @else
        <div class="carte"><div class="carte__corps"><div class="etat-vide"><i data-lucide="hourglass"></i>
            @if (in_array($scrutin->statut, [StatutScrutin::EnPreparation, StatutScrutin::ListesArretees], true))
                <p>Le scrutin n’est pas encore ouvert.@if ($scrutin->date_heure_ouverture) Ouverture le {{ $scrutin->date_heure_ouverture->timezone($fuseau)->format('d/m/Y à H:i') }}.@endif</p>
            @elseif ($scrutin->statut === StatutScrutin::Annule)
                <p>Ce scrutin est annulé.</p>
            @else
                <p>Le scrutin est clos : le vote n’est plus possible.</p>
            @endif
        </div></div></div>
    @endif
</x-layouts.app>
