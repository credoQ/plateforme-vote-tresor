<?php

namespace App\View\Composers;

use App\Enums\Acces\CodeRole;
use App\Models\Agent;
use App\Models\Structure;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Données du gabarit des écrans connectés (components.layouts.app) : menu selon les droits, initiales,
 * profil et affectation de l'utilisateur.
 */
class NavigationComposer
{
    public function compose(View $view): void
    {
        /** @var User $utilisateur */
        $utilisateur = Auth::user();

        $view->with([
            'utilisateur' => $utilisateur,
            'menus' => $this->menus($utilisateur),
            'initiales' => $utilisateur->initiales(),
            'profil' => $utilisateur->roles->first()?->code->libelle() ?? 'Utilisateur',
            'affectationUtilisateur' => $utilisateur->agent?->affectationLe(),
        ]);
    }

    /**
     * Groupes de liens visibles par l'utilisateur ; un groupe vide n'est pas affiché.
     *
     * @return array<string, list<array{cle: string, icone: string, libelle: string, url: string}>>
     */
    private function menus(User $utilisateur): array
    {
        $groupes = [
            'Mon espace' => [
                ['espace', 'house', 'Mon espace électeur', route('espace'), $utilisateur->agent_id !== null],
                ['vote', 'vote', 'Voter', route('vote'), $utilisateur->can('voter')],
                ['palmares', 'trophy', 'Résultats', route('palmares'), true],
            ],
            'Pilotage' => [
                ['editions', 'calendar-range', 'Éditions', route('editions.index'), $utilisateur->can('piloter')],
            ],
            'Suivi' => [
                ['tableau-de-bord', 'layout-dashboard', 'Tableau de bord', route('tableau-de-bord'), $utilisateur->can('observer') || $utilisateur->can('controler-listes')],
                ['journal', 'shield-check', 'Journal d’audit', route('journal'), $utilisateur->can('observer')],
            ],
            'Administration' => [
                ['agents', 'id-card', $utilisateur->aLeRole(CodeRole::Administrateur, CodeRole::Comite, CodeRole::Observateur) ? 'Agents' : 'Agents de ma structure', route('agents.index'), $utilisateur->can('viewAny', Agent::class)],
                ['structures', 'building-2', 'Structures', route('structures.index'), $utilisateur->can('viewAny', Structure::class)],
                ['utilisateurs', 'users', 'Utilisateurs', route('utilisateurs.index'), $utilisateur->can('administrer')],
                ['taches-echouees', 'rotate-ccw', 'Tâches en échec', route('taches-echouees.index'), $utilisateur->can('administrer')],
            ],
        ];

        return collect($groupes)
            ->map(fn (array $liens): array => collect($liens)
                ->filter(fn (array $lien): bool => $lien[4])
                ->map(fn (array $lien): array => ['cle' => $lien[0], 'icone' => $lien[1], 'libelle' => $lien[2], 'url' => $lien[3]])
                ->values()->all())
            ->filter()
            ->all();
    }
}
