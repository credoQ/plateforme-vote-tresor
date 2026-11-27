<?php

namespace App\Actions\Acces;

use App\Actions\Audit\Journaliser;
use App\Enums\Acces\CodeRole;
use App\Enums\Audit\ActionAuditee;
use App\Models\Affectation;
use App\Models\Role;
use App\Models\Structure;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Coupons des codes d'accès (mode « code imprimé ») en PDF, à découper et remettre sous pli par le comité :
 * - « electeurs » : les comptes qui n'ont que le rôle d'électeur, une section par structure d'affectation ;
 * - « autres » : les comptes qui ont un autre rôle (comité, administration, observation, point focal), à part ;
 * - un seul compte, après une régénération.
 * Seuls les comptes actifs qui ont un code sont imprimés. Chaque impression est tracée (R56), sans les codes.
 */
class ImprimerCodesAcces
{
    public const ELECTEURS = 'electeurs';

    public const AUTRES = 'autres';

    public function __construct(private Journaliser $journal) {}

    /**
     * @return array{pdf: string, nom: string, nombre: int}
     */
    public function handle(string $lot, ?User $auteur = null): array
    {
        $comptes = $this->comptes($lot);
        $sections = $lot === self::ELECTEURS
            ? $this->parStructure($comptes)
            : collect(['Comptes de gestion (comité, administration, observation, points focaux)' => $comptes->sortBy('name')->values()]);

        return $this->rendre($sections, $lot === self::ELECTEURS ? 'Codes d’accès des électeurs' : 'Codes d’accès des comptes de gestion', "codes-acces-{$lot}.pdf", $lot, $auteur);
    }

    /**
     * Coupons des comptes choisis à l'écran (identifiants), quel que soit leur rôle, par structure d'affectation.
     *
     * @param  list<int>  $comptes
     * @return array{pdf: string, nom: string, nombre: int}
     */
    public function selection(array $comptes, ?User $auteur = null): array
    {
        $choisis = User::query()->where('actif', true)->whereNotNull('code_acces')->whereKey($comptes)->with(['roles', 'agent'])->get();

        return $this->rendre($this->parStructure($choisis), 'Codes d’accès', 'codes-acces-selection.pdf', 'sélection', $auteur);
    }

    /**
     * Coupon d'un seul compte (code perdu, puis régénéré).
     *
     * @return array{pdf: string, nom: string, nombre: int}
     */
    public function coupon(User $compte, ?User $auteur = null): array
    {
        $compte->loadMissing(['roles', 'agent']);
        $section = $this->parStructure(collect([$compte]));

        return $this->rendre($section, 'Code d’accès de '.$compte->name, "code-acces-{$compte->login}.pdf", 'coupon '.$compte->login, $auteur);
    }

    /**
     * @return Collection<int, User>
     */
    private function comptes(string $lot): Collection
    {
        $autresRoles = fn (Builder $roles) => $roles->where('code', '!=', CodeRole::Electeur->value);

        return User::query()
            ->where('actif', true)
            ->whereNotNull('code_acces')
            ->with(['roles', 'agent'])
            ->when($lot === self::ELECTEURS, fn (Builder $q) => $q->whereNotNull('agent_id')->whereDoesntHave('roles', $autresRoles))
            ->when($lot === self::AUTRES, fn (Builder $q) => $q->whereHas('roles', $autresRoles))
            ->get();
    }

    /**
     * Comptes rangés par structure d'affectation en cours de leur agent, structures par ordre de code.
     *
     * @param  Collection<int, User>  $comptes
     * @return Collection<string, Collection<int, User>>
     */
    private function parStructure(Collection $comptes): Collection
    {
        $affectations = Affectation::enCoursLe(now())->whereIn('agent_id', $comptes->pluck('agent_id')->filter())->pluck('structure_id', 'agent_id');
        $structures = Structure::whereKey($affectations->values()->unique())->get(['id', 'code', 'libelle'])->keyBy('id');

        return $comptes
            ->groupBy(function (User $compte) use ($affectations, $structures): string {
                $structure = $structures[$affectations[$compte->agent_id] ?? 0] ?? null;

                return $structure !== null ? "{$structure->code} · {$structure->libelle}" : 'Sans affectation en cours';
            })
            ->map(fn (Collection $groupe): Collection => $groupe->sortBy('name')->values())
            ->sortKeys();
    }

    /**
     * @param  Collection<string, Collection<int, User>>  $sections
     * @return array{pdf: string, nom: string, nombre: int}
     */
    private function rendre(Collection $sections, string $titre, string $nom, string $lot, ?User $auteur): array
    {
        $nombre = $sections->sum(fn (Collection $groupe): int => $groupe->count());

        $html = view('acces.codes-acces-pdf', [
            'titre' => $titre,
            // Deux coupons par ligne.
            'sections' => $sections->map(fn (Collection $groupe): array => [
                'nombre' => $groupe->count(),
                'lignes' => $groupe->map(fn (User $compte): array => [
                    'nom' => $compte->name,
                    'login' => $compte->login,
                    'roles' => $compte->roles->map(fn (Role $role): string => $role->code->libelle())->implode(', '),
                    'code' => GenererCodesAcces::formater($compte->code_acces),
                ])->chunk(2)->map(fn (Collection $ligne): array => $ligne->values()->all())->values()->all(),
            ])->all(),
            'adresse' => config('app.url'),
            'auteur' => $auteur?->name,
        ])->render();

        $this->journal->handle(ActionAuditee::CodesAccesImprimes, null, ['lot' => $lot, 'nombre' => $nombre], $auteur);

        return ['pdf' => Pdf::loadHTML($html)->setPaper('a4')->output(), 'nom' => $nom, 'nombre' => $nombre];
    }
}
