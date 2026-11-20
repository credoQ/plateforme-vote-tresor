@use('App\Enums\Scrutins\StatutScrutin')
<x-layouts.vote titre="Voter" :contexte="$scrutin ? $scrutin->phase->libelle.' · '.$scrutin->libelle : null">
    <div class="titre-page isoloir__centre">
        <div>
            <div class="surtitre">{{ $scrutin?->phase->libelle ?? 'Vote' }}</div>
            <h1>{{ $scrutin?->libelle ?? 'Aucun scrutin en cours' }}</h1>
        </div>
    </div>

    @if ($inscription === null)
        <div class="carte isoloir__centre"><div class="carte__corps"><div class="etat-vide"><i data-lucide="calendar-clock"></i>
            <p>Vous n’êtes inscrit sur aucune liste électorale de la phase en cours.</p>
        </div></div></div>
    @elseif ($inscription->a_vote)
        <section class="carte accuse-vote">
            <div class="carte__entete">
                <span class="pastille-icone pastille-icone--succes"><i data-lucide="circle-check"></i></span>
                <div><h2>{{ session('vote_enregistre') ? 'Votre vote est enregistré' : 'Vous avez voté' }}</h2></div>
            </div>
            <div class="carte__corps">
                <table class="tableau">
                    <tbody>
                        <tr><td>Accusé n°</td><td class="nombre reference">{{ $inscription->numero_accuse }}</td></tr>
                        <tr><td>Scrutin</td><td class="nombre">{{ $scrutin->libelle }}</td></tr>
                        <tr><td>Date et heure</td><td class="nombre">{{ $inscription->creneauDeVote() }}</td></tr>
                    </tbody>
                </table>
                @if (session('accuse_envoye'))
                    <p class="aide"><i data-lucide="mail"></i> L’accusé de vote vous est aussi envoyé par e-mail.</p>
                @endif
                <div class="formulaire__actions">
                    <a href="{{ route('vote.accuse', $inscription->numero_accuse) }}" class="btn btn-secondaire"><i data-lucide="download"></i>Télécharger l’accusé (PDF)</a>
                    <a href="{{ route('espace') }}" class="btn btn-primaire">Terminer</a>
                </div>
            </div>
        </section>
    @else
        <div class="carte isoloir__centre"><div class="carte__corps"><div class="etat-vide"><i data-lucide="hourglass"></i>
            @if (in_array($scrutin->statut, [StatutScrutin::EnPreparation, StatutScrutin::ListesArretees], true))
                <p>Le scrutin n’est pas encore ouvert.@if ($scrutin->date_heure_ouverture) Ouverture le {{ $scrutin->date_heure_ouverture->timezone('Africa/Porto-Novo')->format('d/m/Y à H:i') }}.@endif</p>
            @elseif ($scrutin->statut === StatutScrutin::Annule)
                <p>Ce scrutin est annulé.</p>
            @else
                <p>Le scrutin est clos : le vote n’est plus possible.</p>
            @endif
        </div></div></div>
    @endif
</x-layouts.vote>
