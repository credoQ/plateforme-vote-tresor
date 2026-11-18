<x-layouts.app titre="Journal d'audit" :ariane="['Suivi', 'Journal d’audit']" actif="journal">
    <div class="titre-page">
        <div>
            <div class="surtitre">Suivi</div>
            <h1>Journal d’audit</h1>
            <p>Opérations sensibles de la plateforme, en ajout seul : aucune entrée ne peut être modifiée ni supprimée. Les votes n’y figurent jamais.</p>
        </div>
    </div>

    <div class="carte">
        <form method="GET" class="barre-filtres">
            <select name="action" class="champ" aria-label="Filtrer par opération">
                <option value="">Toutes les opérations</option>
                @foreach (\App\Enums\Audit\ActionAuditee::cases() as $cas)
                    <option value="{{ $cas->value }}" @selected($action === $cas)>{{ $cas->libelle() }}</option>
                @endforeach
            </select>
            <input name="login" class="champ" value="{{ $login }}" placeholder="Utilisateur" aria-label="Filtrer par utilisateur">
            <input name="du" type="date" class="champ" value="{{ $du }}" aria-label="Du">
            <input name="au" type="date" class="champ" value="{{ $au }}" aria-label="Au">
            <button type="submit" class="btn btn-secondaire"><i data-lucide="filter"></i>Filtrer</button>
        </form>

        <div class="tableau-defilant">
            <table class="tableau">
                <thead><tr><th>Date et heure</th><th>Utilisateur</th><th>Opération</th><th>Objet</th><th>Détails</th><th>Adresse IP</th></tr></thead>
                <tbody>
                    @forelse ($entrees as $entree)
                        <tr>
                            <td>{{ $entree->date_heure->timezone('Africa/Porto-Novo')->format('d/m/Y H:i:s') }}</td>
                            <td>{{ $entree->login ?? '' }}</td>
                            <td>{{ $entree->action->libelle() }}</td>
                            <td>{{ $entree->entite_type ? $entree->entite_type.' n° '.$entree->entite_id : '' }}</td>
                            <td class="details-audit">{{ $entree->details ? json_encode($entree->details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '' }}</td>
                            <td>{{ $entree->adresse_ip }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><div class="etat-vide"><i data-lucide="shield-check"></i><p>Aucune opération ne correspond aux filtres.</p></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $entrees->links() }}
    </div>
</x-layouts.app>
