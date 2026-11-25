<?php

namespace App\Livewire\Scrutins;

use App\Enums\Acces\CodeRole;
use App\Enums\Referentiel\CodeTypeStructure;
use App\Enums\Scrutins\OrigineCandidate;
use App\Enums\Scrutins\StatutCandidate;
use App\Enums\Scrutins\StatutScrutin;
use App\Http\Controllers\Scrutins\ScrutinController;
use App\Models\Agent;
use App\Models\Candidate;
use App\Models\Inscription;
use App\Models\Scrutin;
use App\Models\Structure;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator as Paginateur;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ViewErrorBag;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Onglets de la fiche d'un scrutin (module 05) : candidates, liste électorale, zone de vote et résultats.
 * Changer d'onglet, filtrer ou paginer ne met à jour que cette zone. Le point focal ne voit que les candidates
 * et les inscrits de son périmètre (R4). Les actions (désactivation, nomination, validation…) restent des
 * formulaires envoyés aux contrôleurs : le composant ne fait que lire et filtrer.
 */
class ContenuDuScrutin extends Component
{
    use WithPagination;

    /**
     * Onglets de la zone.
     */
    public const ONGLETS = ['candidates', 'liste', 'zone', 'resultats'];

    private const PAGE_CANDIDATES = 'page_candidates';

    public Scrutin $scrutin;

    #[Url]
    public string $onglet = '';

    #[Url(as: 'candidate')]
    public string $rechercheCandidate = '';

    #[Url(as: 'statut_candidate')]
    public string $statutCandidate = '';

    #[Url(as: 'structure_candidate')]
    public string $structureCandidate = '';

    #[Url]
    public string $recherche = '';

    /**
     * Contrôle des droits à chaque requête du composant, mises à jour comprises.
     */
    public function boot(): void
    {
        $utilisateur = $this->utilisateur();
        abort_unless($utilisateur->can('observer') || $utilisateur->can('controler-listes'), 403);
    }

    /**
     * Onglet par défaut : les résultats dès qu'ils sont visibles, sinon les candidates. Les erreurs d'un formulaire
     * envoyé depuis un onglet (nomination, prolongation) restent affichées après le retour sur la fiche.
     */
    public function mount(): void
    {
        $this->scrutin->loadMissing(['phase.edition', 'college', 'procesVerbal', 'structures' => fn ($q) => $q->with(['typeStructure', 'departement'])->orderBy('code')]);

        if (! in_array($this->onglet, self::ONGLETS, true)) {
            $this->onglet = $this->resultatsVisibles() ? 'resultats' : 'candidates';
        }

        $erreurs = session('errors');
        if ($erreurs instanceof ViewErrorBag) {
            $this->setErrorBag($erreurs->getBag('default'));
        }
    }

    public function choisirOnglet(string $onglet): void
    {
        $this->onglet = in_array($onglet, self::ONGLETS, true) ? $onglet : 'candidates';
    }

    public function updated(string $propriete): void
    {
        if (in_array($propriete, ['rechercheCandidate', 'statutCandidate', 'structureCandidate'], true)) {
            $this->resetPage(self::PAGE_CANDIDATES);
        }
        if ($propriete === 'recherche') {
            $this->resetPage();
        }
    }

    /**
     * Structures du point focal, ou null pour les profils qui voient tout le scrutin.
     *
     * @return list<int>|null
     */
    #[Computed]
    public function perimetre(): ?array
    {
        $utilisateur = $this->utilisateur();

        return $utilisateur->aLeRole(CodeRole::Comite, CodeRole::Observateur, CodeRole::Administrateur) ? null : $utilisateur->perimetre();
    }

    /**
     * Photo ajoutée, remplacée ou retirée dans la fenêtre Scrutins\PhotoCandidate : la liste est relue.
     */
    #[On('photo-candidate-modifiee')]
    public function photoModifiee(): void
    {
        unset($this->candidates, $this->pageCandidates);
    }

    /**
     * Candidates du scrutin dans le périmètre, rangées par rang puis par nom, avec leur structure d'inscription.
     *
     * @return Collection<int, Candidate>
     */
    #[Computed]
    public function candidates(): Collection
    {
        $structuresInscription = $this->scrutin->inscriptions()->pluck('structure_id', 'agent_id');

        return $this->scrutin->candidates()->with(['agent', 'desactiveePar', 'nommeePar'])->get()
            ->each(fn (Candidate $c) => $c->setAttribute('structure_inscription_id', $structuresInscription[$c->agent_id] ?? null))
            ->filter(fn (Candidate $c) => $this->perimetre === null || in_array($c->structure_inscription_id, $this->perimetre, true))
            ->sortBy([['rang', 'asc'], [fn (Candidate $c) => $c->agent->nomComplet(), 'asc']])
            ->values();
    }

    /**
     * Candidates de l'onglet : recherche par nom ou matricule, statut et structure d'inscription, paginées.
     *
     * @return LengthAwarePaginator<int, Candidate>
     */
    #[Computed]
    public function pageCandidates(): LengthAwarePaginator
    {
        $terme = mb_strtolower(trim($this->rechercheCandidate));
        $statut = StatutCandidate::tryFrom($this->statutCandidate);
        $structure = (int) $this->structureCandidate ?: null;

        $filtrees = $this->candidates
            ->when($terme !== '', fn (Collection $c) => $c->filter(fn (Candidate $candidate): bool => str_contains(mb_strtolower($candidate->agent->nomComplet().' '.$candidate->agent->matricule), $terme)))
            ->when($statut !== null, fn (Collection $c) => $c->filter(fn (Candidate $candidate): bool => $candidate->statut === $statut))
            ->when($structure !== null, fn (Collection $c) => $c->filter(fn (Candidate $candidate): bool => $candidate->structure_inscription_id === $structure))
            ->values();

        $page = $this->getPage(self::PAGE_CANDIDATES);

        return new LengthAwarePaginator(
            $filtrees->forPage($page, config('app.lignes_par_page'))->values(),
            $filtrees->count(),
            config('app.lignes_par_page'),
            $page,
            ['pageName' => self::PAGE_CANDIDATES],
        );
    }

    /**
     * Inscrits de la liste électorale dans le périmètre, recherchés par matricule ou nom.
     *
     * @return Paginateur<int, Inscription>
     */
    #[Computed]
    public function inscriptions(): Paginateur
    {
        $recherche = trim($this->recherche);

        return $this->scrutin->inscriptions()
            ->with(['agent', 'structure'])
            ->when($this->perimetre !== null, fn ($q) => $q->whereIn('inscriptions.structure_id', $this->perimetre))
            ->when($recherche !== '', fn ($q) => $q->whereHas('agent', fn ($a) => $a
                ->whereLike('matricule', "%{$recherche}%")->orWhereLike('nom', "%{$recherche}%")->orWhereLike('prenoms', "%{$recherche}%")))
            ->join('agents', 'agents.id', '=', 'inscriptions.agent_id')
            ->orderBy('agents.nom')->orderBy('agents.prenoms')
            ->select('inscriptions.*')
            ->paginate(config('app.lignes_par_page'));
    }

    /**
     * Nombre d'inscrits du périmètre, affiché sur l'onglet de la liste électorale.
     */
    #[Computed]
    public function nombreInscrits(): int
    {
        return $this->scrutin->inscriptions()
            ->when($this->perimetre !== null, fn ($q) => $q->whereIn('structure_id', $this->perimetre))
            ->count();
    }

    public function paginationView(): string
    {
        return 'pagination.tresor-livewire';
    }

    public function render(): View
    {
        return view('livewire.scrutins.contenu-du-scrutin', [
            'pilote' => $this->utilisateur()->can('piloter'),
            'fuseau' => ScrutinController::FUSEAU,
            'structures' => $this->scrutin->structures->keyBy('id'),
            'circonscription' => $this->circonscription(),
            'onglets' => [
                'candidates' => ['libelle' => 'Candidates', 'compteur' => $this->candidates->count()],
                'liste' => ['libelle' => 'Liste électorale', 'compteur' => $this->nombreInscrits],
                'zone' => ['libelle' => 'Zone de vote', 'compteur' => $this->scrutin->structures->count()],
                'resultats' => ['libelle' => 'Résultats', 'compteur' => null],
            ],
            'filtresCandidatesActifs' => trim($this->rechercheCandidate) !== '' || $this->statutCandidate !== '' || $this->structureCandidate !== '',
            ...$this->nominations(),
            ...$this->resultats(),
        ]);
    }

    private function utilisateur(): User
    {
        /** @var User */
        return Auth::user();
    }

    private function resultatsVisibles(): bool
    {
        $utilisateur = $this->utilisateur();

        return $this->scrutin->procesVerbal !== null
            && ($this->scrutin->statut === StatutScrutin::ResultatsPublies || $utilisateur->can('piloter') || $utilisateur->can('observer'));
    }

    /**
     * Saisie des nominées par le comité (R58) : ouverte sur un collège à nomination tant que les listes ne sont pas
     * arrêtées, avec les électrices du scrutin pas encore candidates ; femmes modèles inéligibles signalées (R59).
     *
     * @return array{ineligiblesEcartees: Collection<int, string>, nominationOuverte: bool, nominables: Collection<int, Agent>}
     */
    private function nominations(): array
    {
        $scrutin = $this->scrutin;
        $ouverte = $this->utilisateur()->can('piloter')
            && $scrutin->college->origine_candidates === OrigineCandidate::Nomination
            && $scrutin->listesSontModifiables();

        $ineligibles = $scrutin->phase->edition->ineligibles();
        $ecartees = $scrutin->college->origine_candidates === OrigineCandidate::Qualification ? collect() : Agent::femmes()
            ->whereIn('id', $scrutin->inscriptions()->select('agent_id'))
            ->whereIn('matricule', $ineligibles->keys())
            ->orderBy('nom')
            ->get()
            ->map(fn (Agent $agent): string => "{$agent->nomComplet()} (élue en {$ineligibles[$agent->matricule]})");

        return [
            'ineligiblesEcartees' => $ecartees,
            'nominationOuverte' => $ouverte,
            'nominables' => ! $ouverte ? collect() : Agent::femmes()
                ->whereIn('id', $scrutin->inscriptions()->select('agent_id'))
                ->whereNotIn('id', $scrutin->candidates()->select('agent_id'))
                ->orderBy('nom')->orderBy('prenoms')
                ->get(['id', 'matricule', 'nom', 'prenoms']),
        ];
    }

    /**
     * Structures de la zone de vote rangées par département (les structures centrales à part), puis par type.
     *
     * @return list<array{departement: string|null, types: list<array{libelle: string, codes: list<string>}>}>
     */
    private function circonscription(): array
    {
        $ordreTypes = collect([CodeTypeStructure::TresorerieDepartementale, CodeTypeStructure::TresorerieCommunale, ...CodeTypeStructure::cases()])
            ->map(fn (CodeTypeStructure $type): string => $type->value)->unique()->values()->flip();

        return $this->scrutin->structures
            ->groupBy(fn (Structure $structure): string => $structure->departement?->libelle ?? '')
            ->sortKeys()
            ->map(fn (Collection $structures, string $departement): array => [
                'departement' => $departement === '' ? null : $departement,
                'types' => $structures
                    ->groupBy(fn (Structure $structure): string => $structure->typeStructure->code->value)
                    ->sortBy(fn (Collection $groupe, string $type): int => $ordreTypes[$type])
                    ->map(fn (Collection $groupe, string $type): array => [
                        'libelle' => CodeTypeStructure::from($type)->libelle(),
                        'codes' => $groupe->pluck('code')->sort()->values()->all(),
                    ])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * Données des résultats : visibilité, classement des candidates actives et ex-æquo à départager (R47 à R51).
     *
     * @return array<string, mixed>
     */
    private function resultats(): array
    {
        $pv = $this->scrutin->procesVerbal;
        $actives = $this->candidates->filter->estActive()->sortBy([['rang', 'asc']]);
        $rangEnBalance = $pv?->egalite_a_departager ? $actives->where('est_laureate', false)->min('rang') : null;

        return [
            'pv' => $pv,
            'voitResultats' => $this->resultatsVisibles(),
            'nonValide' => $this->scrutin->statut === StatutScrutin::Clos,
            'actives' => $actives,
            'rangEnBalance' => $rangEnBalance,
            'exAequo' => $rangEnBalance === null ? collect() : $actives->where('rang', $rangEnBalance),
            'placesRestantes' => $this->scrutin->nbElues() - $actives->where('est_laureate', true)->count(),
        ];
    }
}
