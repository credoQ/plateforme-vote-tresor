<x-layouts.app titre="Utilisateurs" :ariane="['Administration', 'Utilisateurs']" actif="utilisateurs">
    <div class="titre-page">
        <div>
            <h1>Utilisateurs</h1>
        </div>
        <div class="titre-page__actions">
            <a href="{{ route('utilisateurs.create') }}" class="btn btn-primaire"><i data-lucide="user-plus"></i>Créer un compte</a>
        </div>
    </div>

    <div class="carte">
        <form method="GET" class="barre-filtres">
            <div class="champ-icone"><i data-lucide="search"></i><input name="recherche" class="champ" value="{{ $recherche }}" placeholder="Nom ou identifiant" aria-label="Rechercher un utilisateur"></div>
            <select name="role" class="champ" aria-label="Filtrer par rôle">
                <option value="">Tous les rôles</option>
                @foreach (\App\Enums\Acces\CodeRole::cases() as $code)
                    <option value="{{ $code->value }}" @selected($role === $code)>{{ $code->libelle() }}</option>
                @endforeach
            </select>
            <button type="submit" class="btn btn-secondaire"><i data-lucide="filter"></i>Filtrer</button>
        </form>

        <div class="tableau-defilant">
            <table class="tableau tableau--fiches">
                <thead><tr><th>Identifiant</th><th>Nom</th><th>Rôles</th><th>Structure</th><th>Statut</th><th class="actions">Actions</th></tr></thead>
                <tbody>
                    @forelse ($utilisateurs as $utilisateur)
                        <tr>
                            <td>{{ $utilisateur->login }}</td>
                            <td>{{ $utilisateur->name }}</td>
                            <td>{{ $utilisateur->roles->map(fn ($r) => $r->code->libelle())->implode(', ') }}</td>
                            <td>{{ $utilisateur->structure?->code }}</td>
                            <td>
                                @if ($utilisateur->actif)
                                    <span class="badge badge--succes">Actif</span>
                                @else
                                    <span class="badge badge--erreur">Désactivé</span>
                                @endif
                            </td>
                            <td class="actions"><a href="{{ route('utilisateurs.edit', $utilisateur) }}" class="btn btn-tertiaire btn--sm"><i data-lucide="pencil"></i>Modifier</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><div class="etat-vide"><i data-lucide="users"></i><p>Aucun utilisateur ne correspond à la recherche.</p></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $utilisateurs->links() }}
    </div>
</x-layouts.app>
