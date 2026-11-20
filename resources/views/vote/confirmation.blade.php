<x-layouts.vote titre="Confirmer mon vote" :contexte="$scrutin->phase->libelle.' · '.$scrutin->libelle" :etape="2">
    <div class="titre-page isoloir__centre">
        <div>
            <h1>Confirmer votre vote</h1>
            <p>{{ $scrutin->libelle }}</p>
        </div>
    </div>

    <section class="carte confirmation-vote">
        <div class="carte__corps">
            <div class="choix-confirme">
                <span class="avatar avatar--grand avatar--sans-anneau">
                    @if ($choix) {{ $choix['initiales'] }} @else <i data-lucide="file"></i> @endif
                </span>
                <div>
                    <div class="surtitre">Votre choix</div>
                    <b>{{ $choix['nom'] ?? 'Vote blanc' }}</b>
                    @if ($choix)<div class="aide">{{ $choix['structure'] }}</div>@endif
                </div>
            </div>
            <p>Votre vote sera enregistré de façon anonyme et <strong>ne pourra plus être modifié</strong>. Vous recevrez un accusé de vote sans mention de votre choix.</p>

            <div class="formulaire__actions">
                <a href="{{ route('vote') }}" class="btn btn-secondaire"><i data-lucide="arrow-left"></i>Revenir au bulletin</a>
                <form method="POST" action="{{ route('vote.store') }}">
                    @csrf
                    <input type="hidden" name="candidate" value="{{ $valeur }}">
                    <button type="submit" class="btn btn-primaire btn--lg"><i data-lucide="check"></i>Confirmer mon vote</button>
                </form>
            </div>
        </div>
    </section>
</x-layouts.vote>
