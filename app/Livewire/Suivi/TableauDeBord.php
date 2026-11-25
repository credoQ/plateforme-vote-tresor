<?php

namespace App\Livewire\Suivi;

use App\Actions\Editions\SignalerOuverturesEnAttente;
use App\Actions\Suivi\MesurerParticipation;
use App\Enums\Acces\CodeRole;
use App\Enums\Editions\StatutEdition;
use App\Enums\Editions\StatutPhase;
use App\Enums\Referentiel\CodePoleElectoral;
use App\Enums\Scrutins\StatutScrutin;
use App\Models\Edition;
use App\Models\Phase;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Suivi de la participation d'une phase de l'édition en cours (module 08) : comité, observateur, administrateur,
 * et point focal limité à sa structure (R4). Rafraîchi toutes les minutes pendant le vote ; aucune voix n'est
 * affichée (R46).
 */
class TableauDeBord extends Component
{
    use WithPagination;

    /**
     * Année de l'édition affichée ; vide : l'édition en cours, sinon la plus récente.
     */
    #[Url(as: 'edition')]
    public string $annee = '';

    #[Url]
    public string $phase = '';

    #[Url]
    public string $recherche = '';

    #[Url]
    public string $pole = '';

    #[Url]
    public string $statut = '';

    /**
     * Contrôle des droits à chaque requête du composant, rafraîchissements compris.
     */
    public function boot(): void
    {
        abort_unless($this->utilisateur()->can('observer') || $this->utilisateur()->can('controler-listes'), 403);
    }

    public function updated(string $propriete): void
    {
        // Une autre édition n'a pas les mêmes phases : on revient à sa phase par défaut.
        if ($propriete === 'annee') {
            $this->phase = '';
        }

        $this->resetPage();
    }

    public function choisirPhase(int $numero): void
    {
        $this->phase = (string) $numero;
        $this->resetPage();
    }

    #[Computed]
    public function edition(): ?Edition
    {
        return ((int) $this->annee > 0 ? Edition::with('phases')->where('annee', (int) $this->annee)->first() : null)
            ?? Edition::with('phases')->where('statut', StatutEdition::EnCours->value)->first()
            ?? Edition::with('phases')->orderByDesc('annee')->first();
    }

    /**
     * Éditions proposées par le filtre, la plus récente d'abord : « Édition 2026 · en cours ».
     *
     * @return array<int, string>
     */
    #[Computed]
    public function editions(): array
    {
        return Edition::orderByDesc('annee')->get(['annee', 'statut'])
            ->mapWithKeys(fn (Edition $e): array => [$e->annee => "Édition {$e->annee} · ".mb_strtolower($e->statut->libelle())])
            ->all();
    }

    /**
     * Phase demandée, sinon la phase ouverte, la dernière clôturée ou la première.
     */
    #[Computed]
    public function phaseAffichee(): ?Phase
    {
        $phases = $this->edition?->phases;

        return ((int) $this->phase > 0 ? $phases?->firstWhere('numero', (int) $this->phase) : null)
            ?? $phases?->firstWhere('statut', StatutPhase::Ouverte)
            ?? $phases?->where('statut', StatutPhase::Cloturee)->last()
            ?? $phases?->first();
    }

    /**
     * Structures du point focal, ou null pour les profils qui suivent toute la DGTCP.
     *
     * @return list<int>|null
     */
    #[Computed]
    public function limite(): ?array
    {
        $utilisateur = $this->utilisateur();

        return $utilisateur->aLeRole(CodeRole::Comite, CodeRole::Observateur, CodeRole::Administrateur) ? null : $utilisateur->perimetre();
    }

    /**
     * @return array{global: array{inscrits: int, votants: int, taux: float}, scrutins: Collection<int, array<string, mixed>>, zones: array{departements: array<string, mixed>, structures: array<string, mixed>}, structures: Collection<int, array<string, mixed>>}|null
     */
    #[Computed]
    public function participation(): ?array
    {
        return $this->phaseAffichee === null ? null : app(MesurerParticipation::class)->handle($this->phaseAffichee, $this->limite);
    }

    public function paginationView(): string
    {
        return 'pagination.tresor-livewire';
    }

    /**
     * Données de l'îlot « donnees » (indicateurs, graphiques, tableaux), seul rafraîchi chaque minute : un îlot ne voit
     * pas les variables passées par render(), il lit cette propriété calculée.
     *
     * @return array<string, mixed>|null
     */
    #[Computed]
    public function donnees(): ?array
    {
        if ($this->participation === null) {
            return null;
        }

        $scrutins = $this->participation['scrutins']->map(fn (array $ligne): array => $ligne + ['detail' => $this->detail($ligne)]);

        return [
            'global' => $this->participation['global'],
            'indicateurs' => $this->indicateurs($scrutins),
            'heures' => $this->heures(app(MesurerParticipation::class)->votesParHeure($this->phaseAffichee, $this->limite)),
            'zones' => $this->participation['zones'],
            'structures' => $this->participation['structures'],
            'scrutins' => $this->paginer($this->filtrer($scrutins)),
            'statuts' => $this->statuts($scrutins),
            'poles' => $this->parPole($scrutins),
            'ouverts' => $scrutins->filter(fn (array $l): bool => $l['scrutin']->statut === StatutScrutin::Ouvert)
                ->sortBy(fn (array $l) => $l['scrutin']->date_heure_fermeture)->take(5)->values(),
            'nombreOuverts' => $scrutins->filter(fn (array $l): bool => $l['scrutin']->statut === StatutScrutin::Ouvert)->count(),
            'misAJourLe' => now()->timezone('Africa/Porto-Novo'),
        ];
    }

    public function render(): View
    {
        return view('livewire.suivi.tableau-de-bord', [
            'edition' => $this->edition,
            'editions' => $this->editions,
            'phaseAffichee' => $this->phaseAffichee,
            'poles' => $this->poles(),
            'filtresActifs' => trim($this->recherche) !== '' || $this->pole !== '' || $this->statut !== '',
            'totalScrutins' => $this->donnees === null ? 0 : $this->donnees['scrutins']->total(),
            'restreint' => $this->limite !== null,
            // Scrutins arrivés à leur heure d'ouverture sans que leur phase soit ouverte : à l'attention du comité.
            'alertesOuverture' => $this->edition !== null && Gate::allows('piloter') ? app(SignalerOuverturesEnAttente::class)->handle($this->edition) : [],
        ]);
    }

    private function utilisateur(): User
    {
        /** @var User */
        return Auth::user();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $scrutins
     * @return array{total: int, ouverts: int, termines: int, a_venir: int, candidates: int, desistements: int, fermeture: Carbon|null, restant: string|null}
     */
    private function indicateurs(Collection $scrutins): array
    {
        $statuts = $scrutins->map(fn (array $ligne): StatutScrutin => $ligne['scrutin']->statut);
        $fermeture = $scrutins->filter(fn (array $ligne): bool => $ligne['scrutin']->statut === StatutScrutin::Ouvert)
            ->map(fn (array $ligne) => $ligne['scrutin']->date_heure_fermeture)->filter()->max();

        return [
            'total' => $scrutins->count(),
            'ouverts' => $statuts->filter(fn (StatutScrutin $s): bool => $s === StatutScrutin::Ouvert)->count(),
            'termines' => $statuts->filter(fn (StatutScrutin $s): bool => $s->estTermine())->count(),
            'a_venir' => $statuts->filter(fn (StatutScrutin $s): bool => in_array($s, [StatutScrutin::EnPreparation, StatutScrutin::ListesArretees], true))->count(),
            'candidates' => $scrutins->sum(fn (array $ligne): int => (int) $ligne['scrutin']->candidates_actives),
            'desistements' => $scrutins->sum(fn (array $ligne): int => (int) $ligne['scrutin']->candidates_desistees),
            'fermeture' => $fermeture,
            'restant' => $fermeture === null || $fermeture->isPast() ? null : $this->duree(now()->diffInMinutes($fermeture)),
        ];
    }

    /**
     * Contenu de la fenêtre « détail d'un scrutin », ouverte d'un clic sur sa ligne sans nouvelle requête.
     *
     * @param  array<string, mixed>  $ligne
     * @return array<string, mixed>
     */
    private function detail(array $ligne): array
    {
        $scrutin = $ligne['scrutin'];
        $format = fn ($date): ?string => $date?->timezone('Africa/Porto-Novo')->format('d/m/Y à H:i');

        return [
            'libelle' => $scrutin->libelle,
            'code' => $scrutin->code,
            'statut' => $scrutin->statut->libelle(),
            'badge' => $scrutin->statut->badge(),
            'inscrits' => number_format($ligne['inscrits'], 0, ',', ' '),
            'votants' => number_format($ligne['votants'], 0, ',', ' '),
            'abstention' => number_format($ligne['inscrits'] - $ligne['votants'], 0, ',', ' '),
            'taux' => (float) $ligne['taux'],
            'tauxAffiche' => number_format($ligne['taux'], 1, ',', ' ').' %',
            'candidates' => (int) $scrutin->candidates_actives,
            'ouverture' => $format($scrutin->date_heure_ouverture) ?? 'À planifier',
            'fermeture' => $format($scrutin->date_heure_fermeture) ?? 'À planifier',
            'fiche' => route('scrutins.show', $scrutin),
            'listeElectorale' => route('exports.liste-electorale', [$scrutin, 'format' => 'xlsx']),
            'resultats' => $scrutin->statut === StatutScrutin::ResultatsPublies ? route('exports.resultats', [$scrutin, 'format' => 'xlsx']) : null,
        ];
    }

    /**
     * Scrutins par statut pour le donut, dans l'ordre et les couleurs fixes de la palette des graphiques (--tr-graph-N).
     *
     * @param  Collection<int, array<string, mixed>>  $scrutins
     * @return list<array{libelle: string, valeur: int, couleur: int}>
     */
    private function statuts(Collection $scrutins): array
    {
        $groupes = [
            ['Ouverts', [StatutScrutin::Ouvert], 1],
            ['À venir', [StatutScrutin::EnPreparation, StatutScrutin::ListesArretees], 3],
            ['Clos', [StatutScrutin::Clos, StatutScrutin::ResultatsValides], 5],
            ['Résultats publiés', [StatutScrutin::ResultatsPublies], 2],
            ['Annulés', [StatutScrutin::Annule], 6],
        ];

        return collect($groupes)
            ->map(fn (array $groupe): array => [
                'libelle' => $groupe[0],
                'valeur' => $scrutins->filter(fn (array $l): bool => in_array($l['scrutin']->statut, $groupe[1], true))->count(),
                'couleur' => $groupe[2],
            ])
            ->filter(fn (array $segment): bool => $segment['valeur'] > 0 || $segment['libelle'] !== 'Annulés')
            ->values()
            ->all();
    }

    /**
     * Inscrits, votants et taux par pôle (ou pour toute la DGTCP), pour le donut des votants.
     *
     * @param  Collection<int, array<string, mixed>>  $scrutins
     * @return list<array{libelle: string, inscrits: int, votants: int, taux: float, couleur: int}>
     */
    private function parPole(Collection $scrutins): array
    {
        $couleurs = [CodePoleElectoral::Departemental->value => 1, CodePoleElectoral::Structures->value => 2, Phase::COLLEGE_DGTCP => 3];

        return $scrutins
            ->groupBy(fn (array $l): string => $l['scrutin']->poleElectoral?->code->value ?? Phase::COLLEGE_DGTCP)
            ->sortBy(fn (Collection $lignes, string $pole): int => array_search($pole, array_keys($couleurs), true))
            ->map(fn (Collection $lignes, string $pole): array => [
                'libelle' => $this->poles()[$pole],
                'inscrits' => $inscrits = (int) $lignes->sum('inscrits'),
                'votants' => $votants = (int) $lignes->sum('votants'),
                'taux' => $inscrits === 0 ? 0.0 : round($votants * 100 / $inscrits, 1),
                'couleur' => $couleurs[$pole],
            ])
            ->values()
            ->all();
    }

    /**
     * Barres du graphique « Votes par heure » : libellé, nombre de votes et hauteur relative au maximum.
     *
     * @param  array{jour: CarbonImmutable|null, heures: array<int, int>}  $votes
     * @return array{jour: CarbonImmutable|null, barres: list<array{heure: string, plage: string, votes: int, hauteur: float, maximum: bool}>}
     */
    private function heures(array $votes): array
    {
        $maximum = max([1, ...array_values($votes['heures'])]);

        return [
            'jour' => $votes['jour'],
            'barres' => collect($votes['heures'])->map(fn (int $nombre, int $heure): array => [
                'heure' => $heure.' h',
                'plage' => 'De '.$heure.' h à '.($heure + 1).' h',
                'votes' => $nombre,
                'hauteur' => round($nombre * 100 / $maximum, 1),
                'maximum' => $nombre === $maximum,
            ])->values()->all(),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $scrutins
     * @return Collection<int, array<string, mixed>>
     */
    private function filtrer(Collection $scrutins): Collection
    {
        $terme = mb_strtolower(trim($this->recherche));
        $statut = StatutScrutin::tryFrom($this->statut);

        return $scrutins
            ->when($terme !== '', fn (Collection $c) => $c->filter(fn (array $l): bool => str_contains(mb_strtolower($l['scrutin']->libelle.' '.$l['scrutin']->code), $terme)))
            ->when($this->pole !== '', fn (Collection $c) => $c->filter(fn (array $l): bool => ($l['scrutin']->poleElectoral?->code->value ?? Phase::COLLEGE_DGTCP) === $this->pole))
            ->when($statut !== null, fn (Collection $c) => $c->filter(fn (array $l): bool => $l['scrutin']->statut === $statut))
            ->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $scrutins
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    private function paginer(Collection $scrutins): LengthAwarePaginator
    {
        $page = $this->getPage();

        return new LengthAwarePaginator(
            $scrutins->forPage($page, config('app.lignes_par_page'))->values(),
            $scrutins->count(),
            config('app.lignes_par_page'),
            $page,
        );
    }

    /**
     * « 2 j 6 h », « 5 h 20 min » ou « 12 min ».
     */
    private function duree(int|float $minutes): string
    {
        $minutes = (int) $minutes;
        $jours = intdiv($minutes, 1440);
        $heures = intdiv($minutes % 1440, 60);

        return match (true) {
            $jours > 0 => "{$jours} j {$heures} h",
            $heures > 0 => "{$heures} h ".($minutes % 60).' min',
            default => "{$minutes} min",
        };
    }

    /**
     * Pôles proposés par le filtre des scrutins.
     *
     * @return array<string, string>
     */
    private function poles(): array
    {
        return [
            CodePoleElectoral::Departemental->value => CodePoleElectoral::Departemental->libelle(),
            CodePoleElectoral::Structures->value => CodePoleElectoral::Structures->libelle(),
            Phase::COLLEGE_DGTCP => 'Toute la DGTCP',
        ];
    }
}
