<?php

namespace App\Http\Controllers\Scrutins;

use App\Actions\Scrutins\ArreterListes;
use App\Actions\Scrutins\ConstituerListes;
use App\Actions\Scrutins\GenererScrutins;
use App\Actions\Scrutins\PlanifierScrutin;
use App\Actions\Scrutins\RouvrirListes;
use App\Enums\Acces\CodeRole;
use App\Enums\Referentiel\CodeTypeStructure;
use App\Enums\Scrutins\OrigineCandidate;
use App\Enums\Scrutins\StatutCandidate;
use App\Enums\Scrutins\StatutScrutin;
use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\Candidate;
use App\Models\Phase;
use App\Models\Scrutin;
use App\Models\Structure;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Écrans des scrutins (module 05) : génération, planification, contrôle et arrêt des listes, candidates.
 * Le point focal consulte les listes et candidates de sa seule structure (R4).
 */
class ScrutinController extends Controller
{
    public const FUSEAU = 'Africa/Porto-Novo';

    private const PAGE_CANDIDATES = 'page_candidates';

    public function generer(Phase $phase, GenererScrutins $generer): RedirectResponse
    {
        $rapport = $generer->handle($phase);

        return redirect()->route('editions.show', [$phase->edition_id, 'phase' => $phase->numero])
            ->with('succes', "{$rapport->scrutins->count()} scrutin(s) générés pour la {$phase->libelle}.")
            ->with('sans_femme', $rapport->circonscriptionsSansFemme);
    }

    public function show(Request $request, Scrutin $scrutin): View
    {
        $utilisateur = $request->user();
        abort_unless($utilisateur->can('observer') || $utilisateur->can('controler-listes'), 403);
        $perimetre = $utilisateur->aLeRole(CodeRole::Comite, CodeRole::Observateur, CodeRole::Administrateur) ? null : $utilisateur->perimetre();
        $recherche = trim((string) $request->query('recherche', ''));

        $scrutin->load(['phase.edition', 'college.poleElectoral', 'poleElectoral', 'structures' => fn ($q) => $q->with(['typeStructure', 'departement'])->orderBy('code'), 'procesVerbal']);

        $structuresInscription = $scrutin->inscriptions()->pluck('structure_id', 'agent_id');
        $candidates = $scrutin->candidates()->with(['agent', 'desactiveePar', 'nommeePar'])->get()
            ->each(fn ($c) => $c->setAttribute('structure_inscription_id', $structuresInscription[$c->agent_id] ?? null))
            ->filter(fn ($c) => $perimetre === null || in_array($c->structure_inscription_id, $perimetre, true))
            ->sortBy([['rang', 'asc'], [fn ($c) => $c->agent->nomComplet(), 'asc']])
            ->values();

        $filtresCandidates = [
            'candidate' => trim((string) $request->query('candidate', '')),
            'statut_candidate' => StatutCandidate::tryFrom((string) $request->query('statut_candidate', '')),
            'structure_candidate' => $request->integer('structure_candidate') ?: null,
        ];

        $inscriptions = $scrutin->inscriptions()
            ->with(['agent', 'structure'])
            ->when($perimetre !== null, fn ($q) => $q->whereIn('inscriptions.structure_id', $perimetre))
            ->when($recherche !== '', fn ($q) => $q->whereHas('agent', fn ($a) => $a
                ->whereLike('matricule', "%{$recherche}%")->orWhereLike('nom', "%{$recherche}%")->orWhereLike('prenoms', "%{$recherche}%")))
            ->join('agents', 'agents.id', '=', 'inscriptions.agent_id')
            ->orderBy('agents.nom')->orderBy('agents.prenoms')
            ->select('inscriptions.*')
            ->paginate(config('app.lignes_par_page'))
            ->withQueryString();

        return view('scrutins.show', [
            'scrutin' => $scrutin,
            'candidates' => $candidates,
            'pageCandidates' => $this->pageCandidates($candidates, $filtresCandidates, $request),
            'filtresCandidates' => $filtresCandidates,
            'inscriptions' => $inscriptions->fragment('liste-electorale'),
            'recherche' => $recherche,
            'restreint' => $perimetre !== null,
            'fuseau' => self::FUSEAU,
            'pilote' => $utilisateur->can('piloter'),
            ...$this->nominations($scrutin, $utilisateur),
            'structures' => $scrutin->structures->keyBy('id'),
            'circonscription' => $this->circonscription($scrutin),
            'participation' => $scrutin->participation(),
            ...$this->resultats($scrutin, $candidates, $utilisateur),
        ]);
    }

    /**
     * Candidates affichées dans le tableau : recherche par nom ou matricule, statut et structure d'inscription,
     * 25 par page (paramètre de page distinct de celui de la liste électorale).
     *
     * @param  Collection<int, Candidate>  $candidates
     * @param  array{candidate: string, statut_candidate: StatutCandidate|null, structure_candidate: int|null}  $filtres
     * @return LengthAwarePaginator<int, Candidate>
     */
    private function pageCandidates(Collection $candidates, array $filtres, Request $request): LengthAwarePaginator
    {
        $terme = mb_strtolower($filtres['candidate']);
        $filtrees = $candidates
            ->when($terme !== '', fn (Collection $c) => $c->filter(fn (Candidate $candidate): bool => str_contains(mb_strtolower($candidate->agent->nomComplet().' '.$candidate->agent->matricule), $terme)))
            ->when($filtres['statut_candidate'] !== null, fn (Collection $c) => $c->filter(fn (Candidate $candidate): bool => $candidate->statut === $filtres['statut_candidate']))
            ->when($filtres['structure_candidate'] !== null, fn (Collection $c) => $c->filter(fn (Candidate $candidate): bool => $candidate->structure_inscription_id === $filtres['structure_candidate']))
            ->values();

        $page = LengthAwarePaginator::resolveCurrentPage(self::PAGE_CANDIDATES);

        return (new LengthAwarePaginator(
            $filtrees->forPage($page, config('app.lignes_par_page'))->values(),
            $filtrees->count(),
            config('app.lignes_par_page'),
            $page,
            ['path' => $request->url(), 'query' => $request->query(), 'pageName' => self::PAGE_CANDIDATES],
        ))->fragment('candidates');
    }

    /**
     * Saisie des nominées par le comité (R58) : ouverte sur un collège à nomination tant que les listes ne sont pas arrêtées,
     * avec les électrices du scrutin pas encore candidates pour la saisie du matricule.
     *
     * @return array{ineligiblesEcartees: Collection<int, string>, nominationOuverte: bool, nominables: Collection<int, Agent>}
     */
    private function nominations(Scrutin $scrutin, User $utilisateur): array
    {
        $ouverte = $utilisateur->can('piloter')
            && $scrutin->college->origine_candidates === OrigineCandidate::Nomination
            && $scrutin->listesSontModifiables();

        // R59 : électrices du scrutin écartées des candidates parce que femmes modèles encore inéligibles.
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
     * Structures de la circonscription rangées par département (les structures centrales à part), puis par type :
     * la trésorerie départementale avant les trésoreries communales, dans l'ordre des types du référentiel.
     *
     * @return list<array{departement: string|null, types: list<array{libelle: string, codes: list<string>}>}>
     */
    private function circonscription(Scrutin $scrutin): array
    {
        $ordreTypes = collect([CodeTypeStructure::TresorerieDepartementale, CodeTypeStructure::TresorerieCommunale, ...CodeTypeStructure::cases()])
            ->map(fn (CodeTypeStructure $type): string => $type->value)->unique()->values()->flip();

        return $scrutin->structures
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
     * Données de la partie « Dépouillement et résultats » de l'écran : visibilité des résultats,
     * classement des candidates actives et, en cas d'égalité, ex-æquo à départager.
     *
     * @param  Collection<int, Candidate>  $candidates
     * @return array<string, mixed>
     */
    private function resultats(Scrutin $scrutin, Collection $candidates, User $utilisateur): array
    {
        $pv = $scrutin->procesVerbal;
        $actives = $candidates->filter->estActive()->sortBy([['rang', 'asc']]);
        $rangEnBalance = $pv?->egalite_a_departager ? $actives->where('est_laureate', false)->min('rang') : null;

        return [
            'pv' => $pv,
            'voitResultats' => $pv !== null && ($scrutin->statut === StatutScrutin::ResultatsPublies || $utilisateur->can('piloter') || $utilisateur->can('observer')),
            'nonValide' => $scrutin->statut === StatutScrutin::Clos,
            'actives' => $actives,
            'rangEnBalance' => $rangEnBalance,
            'exAequo' => $rangEnBalance === null ? collect() : $actives->where('rang', $rangEnBalance),
            'placesRestantes' => $scrutin->nbElues() - $actives->where('est_laureate', true)->count(),
        ];
    }

    public function planifier(Request $request, Scrutin $scrutin, PlanifierScrutin $planifier): RedirectResponse
    {
        $donnees = $request->validate([
            'ouverture' => ['required', 'date_format:Y-m-d\TH:i'],
            'fermeture' => ['required', 'date_format:Y-m-d\TH:i'],
        ], ['required' => 'Indiquez la date et l’heure.', 'date_format' => 'Date et heure invalides.']);

        $planifier->handle(
            $scrutin,
            Carbon::createFromFormat('Y-m-d\TH:i', $donnees['ouverture'], self::FUSEAU)->utc(),
            Carbon::createFromFormat('Y-m-d\TH:i', $donnees['fermeture'], self::FUSEAU)->utc(),
        );

        return redirect()->route('scrutins.show', $scrutin)->with('succes', 'Le scrutin est planifié.');
    }

    public function actualiser(Scrutin $scrutin, ConstituerListes $constituer): RedirectResponse
    {
        $scrutin = $constituer->handle($scrutin);

        return redirect()->route('scrutins.show', $scrutin)
            ->with('succes', "Listes actualisées : {$scrutin->inscriptions_count} inscrit(s), {$scrutin->candidates_count} candidate(s).");
    }

    public function arreter(Scrutin $scrutin, ArreterListes $arreter): RedirectResponse
    {
        try {
            $arreter->handle($scrutin);
        } catch (ValidationException $exception) {
            return redirect()->route('scrutins.show', $scrutin)->withErrors($exception->errors());
        }

        return redirect()->route('scrutins.show', $scrutin)->with('succes', 'Les listes sont arrêtées : elles ne seront plus modifiées.');
    }

    /**
     * R27 : réouverture motivée des listes arrêtées, pour les corriger avant l'ouverture du scrutin.
     */
    public function rouvrir(Request $request, Scrutin $scrutin, RouvrirListes $rouvrir): RedirectResponse
    {
        $motif = $request->validate(['motif' => ['required', 'string', 'max:500']], [
            'motif.required' => 'Indiquez le motif de la décision du comité.',
            'motif.max' => 'Le motif ne dépasse pas 500 caractères.',
        ])['motif'];

        try {
            $rouvrir->handle($scrutin, $motif);
        } catch (ValidationException $exception) {
            return redirect()->route('scrutins.show', $scrutin)->withErrors($exception->errors());
        }

        return redirect()->route('scrutins.show', $scrutin)->with('succes', 'Les listes sont rouvertes : corrigez les agents, actualisez puis arrêtez de nouveau les listes.');
    }
}
