<x-layouts.app titre="Journal d'audit" :ariane="['Suivi', 'Journal d’audit']" actif="journal">
    <div class="titre-page">
        <div>
            <h1>Journal d’audit</h1>
        </div>
    </div>

    <div class="carte">
        <x-barre-filtres :total="$entrees->total()" libelle="opération(s)">
            <x-filtre libelle="Opération" pour="filtre-action" large>
                <x-select-recherche compact soumettre>
                    <select id="filtre-action" name="action" class="champ">
                        <option value="">Toutes les opérations</option>
                        @foreach (\App\Enums\Audit\ActionAuditee::cases() as $cas)
                            <option value="{{ $cas->value }}" @selected($action === $cas)>{{ $cas->libelle() }}</option>
                        @endforeach
                    </select>
                </x-select-recherche>
            </x-filtre>
            <x-filtre libelle="Utilisateur" pour="filtre-login">
                <input id="filtre-login" name="login" class="champ" value="{{ $login }}" placeholder="Identifiant">
            </x-filtre>
            <x-filtre libelle="Du" pour="filtre-du">
                <input id="filtre-du" name="du" type="date" class="champ" value="{{ $du }}">
            </x-filtre>
            <x-filtre libelle="Au" pour="filtre-au">
                <input id="filtre-au" name="au" type="date" class="champ" value="{{ $au }}">
            </x-filtre>
        </x-barre-filtres>

        <div class="tableau-defilant">
            <table class="tableau tableau--fiches">
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
        {{ $entrees->onEachSide(1)->links('pagination.tresor', ['libelle' => 'opérations']) }}
    </div>
</x-layouts.app>
