@props(['phase'])
@use('App\Enums\Editions\StatutPhase')
@if ($phase->scrutins->isEmpty())
    <div class="etat-vide">
        <i data-lucide="clipboard-list"></i>
        <p>Les scrutins de cette phase ne sont pas encore générés.</p>
        @if ($phase->statut === StatutPhase::Planifiee)
            <form method="POST" action="{{ route('phases.scrutins.generer', $phase) }}">
                @csrf
                <button type="submit" class="btn btn-secondaire"><i data-lucide="wand-sparkles"></i>Générer les scrutins</button>
            </form>
        @endif
    </div>
@else
    <div class="tableau-defilant espace-bloc">
        <table class="tableau tableau--fiches">
            <thead><tr><th>Scrutin</th><th class="nombre">Inscrits</th><th class="nombre">Candidates</th><th>Ouverture</th><th>Statut</th><th class="actions"></th></tr></thead>
            <tbody>
                @foreach ($phase->scrutins as $scrutin)
                    <tr>
                        <td>{{ $scrutin->libelle }}<div class="aide">{{ $scrutin->code }}</div></td>
                        <td class="nombre">{{ number_format($scrutin->inscriptions_count, 0, ',', ' ') }}</td>
                        <td class="nombre">{{ $scrutin->candidates_actives_count }}</td>
                        <td>{{ $scrutin->date_heure_ouverture?->timezone('Africa/Porto-Novo')->format('d/m/Y H:i') ?? 'À planifier' }}</td>
                        <td><span class="badge {{ $scrutin->statut->badge() }}">{{ $scrutin->statut->libelle() }}</span></td>
                        <td class="actions"><a href="{{ route('scrutins.show', $scrutin) }}" class="btn btn-tertiaire btn--sm"><i data-lucide="eye"></i>Ouvrir</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
@if (session('sans_femme') && $phase->numero === 1 && $phase->scrutins->isNotEmpty())
    <div class="alerte alerte--info espace-bloc"><i data-lucide="info"></i>
        <div><strong>{{ count(session('sans_femme')) }} circonscription(s) sans scrutin</strong>, faute d’agent femme en service à la date de gel : {{ implode(', ', session('sans_femme')) }}. Leurs agents voteront aux phases 2 et 3.</div>
    </div>
@endif
