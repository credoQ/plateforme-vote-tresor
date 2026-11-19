<?php

namespace App\Http\Controllers\Editions;

use App\Actions\Editions\EnregistrerEdition;
use App\Actions\Editions\LancerEdition;
use App\Enums\Editions\Decoupage;
use App\Enums\Editions\StatutEdition;
use App\Enums\Editions\StatutPhase;
use App\Enums\Referentiel\CodePoleElectoral;
use App\Enums\Scrutins\OrigineCandidate;
use App\Enums\Scrutins\StatutScrutin;
use App\Http\Controllers\Controller;
use App\Models\Edition;
use App\Models\Phase;
use App\Models\Scrutin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Écrans du comité : création, paramétrage (phases et collèges compris) et lancement des éditions (R16, R17).
 */
class EditionController extends Controller
{
    /**
     * Valeur du formulaire pour un collège sans scrutin dans la phase.
     */
    private const AUCUN_SCRUTIN = 'AUCUN';

    /**
     * Onglets de la phase affichée sur la fiche de l'édition.
     */
    private const ONGLETS = ['scrutins', 'colleges', 'resultats'];

    public function index(Request $request): View
    {
        $recherche = trim((string) $request->query('recherche', ''));
        $statut = StatutEdition::tryFrom((string) $request->query('statut', ''));

        $editions = Edition::withCount('scrutins')
            ->when($recherche !== '', fn ($q) => $q->where(fn ($r) => $r
                ->whereLike('libelle', "%{$recherche}%")
                ->when(ctype_digit($recherche), fn ($a) => $a->orWhere('annee', (int) $recherche))))
            ->when($statut !== null, fn ($q) => $q->where('statut', $statut->value))
            ->orderByDesc('annee')
            ->paginate(25)
            ->withQueryString();

        return view('editions.index', [
            'editions' => $editions,
            'recherche' => $recherche,
            'statut' => $statut,
        ]);
    }

    public function create(): View
    {
        return $this->formulaire(new Edition(['annee' => now()->year]));
    }

    public function store(Request $request, EnregistrerEdition $enregistrer): RedirectResponse
    {
        $edition = $enregistrer->handle($this->donnees($request));

        return redirect()->route('editions.show', $edition)->with('succes', "L’édition {$edition->annee} est créée avec {$edition->phases->count()} phase(s).");
    }

    /**
     * Fiche de l'édition : frise des phases, puis la phase choisie (?phase=) avec ses scrutins, ses collèges
     * ou ses résultats à valider (?onglet=). Par défaut, la phase ouverte, sinon la première non clôturée.
     */
    public function show(Request $request, Edition $edition): View
    {
        $edition->load(['phases' => fn ($q) => $q->withCount([
            'scrutins',
            'scrutins as scrutins_termines_count' => fn ($s) => $s->whereIn('statut', [StatutScrutin::ResultatsPublies->value, StatutScrutin::Annule->value]),
            'scrutins as scrutins_a_valider_count' => fn ($s) => $s->whereIn('statut', [StatutScrutin::Clos->value, StatutScrutin::ResultatsValides->value]),
        ]), 'phases.colleges.poleElectoral']);

        $phase = ($request->integer('phase') ? $edition->phases->firstWhere('numero', $request->integer('phase')) : null)
            ?? $edition->phases->firstWhere('statut', StatutPhase::Ouverte)
            ?? $edition->phases->first(fn (Phase $p): bool => $p->statut !== StatutPhase::Cloturee)
            ?? $edition->phases->last();

        $onglet = in_array($request->query('onglet'), self::ONGLETS, true) ? $request->query('onglet') : 'scrutins';
        $recherche = trim((string) $request->query('recherche', ''));
        $college = (string) $request->query('college', '');
        $statut = StatutScrutin::tryFrom((string) $request->query('statut', ''));

        $scrutins = $phase === null ? null : $phase->scrutins()
            ->withCount([
                'inscriptions',
                'inscriptions as votants_count' => fn ($i) => $i->where('a_vote', true),
                'candidates as candidates_actives_count' => fn ($c) => $c->actives(),
            ])
            ->when($onglet === 'resultats', fn ($q) => $q->whereIn('statut', [StatutScrutin::Clos->value, StatutScrutin::ResultatsValides->value]))
            ->when($recherche !== '', fn ($q) => $q->where(fn ($r) => $r->whereLike('libelle', "%{$recherche}%")->orWhereLike('code', "%{$recherche}%")))
            ->when($college === Phase::COLLEGE_DGTCP, fn ($q) => $q->whereNull('pole_electoral_id'))
            ->when(CodePoleElectoral::tryFrom($college) !== null, fn ($q) => $q->whereHas('poleElectoral', fn ($p) => $p->where('code', $college)))
            ->when($statut !== null, fn ($q) => $q->where('statut', $statut->value))
            ->orderBy('code')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Scrutin $scrutin): array => [
                'scrutin' => $scrutin,
                'taux' => $scrutin->inscriptions_count === 0 ? null : round($scrutin->votants_count * 100 / $scrutin->inscriptions_count, 1),
            ]);

        return view('editions.show', [
            'edition' => $edition,
            'phase' => $phase,
            'onglet' => $onglet,
            'scrutins' => $scrutins,
            'recherche' => $recherche,
            'college' => $college,
            'statut' => $statut,
            'colleges' => [
                CodePoleElectoral::Departemental->value => CodePoleElectoral::Departemental->libelle(),
                CodePoleElectoral::Structures->value => CodePoleElectoral::Structures->libelle(),
                Phase::COLLEGE_DGTCP => 'Ensemble de la DGTCP',
            ],
            'clotureImpossible' => $phase !== null && $phase->scrutins_termines_count < $phase->scrutins_count,
        ]);
    }

    public function edit(Edition $edition): View
    {
        return $this->formulaire($edition->load('phases.colleges.poleElectoral'));
    }

    public function update(Request $request, Edition $edition, EnregistrerEdition $enregistrer): RedirectResponse
    {
        $enregistrer->handle($this->donnees($request), $edition);

        return redirect()->route('editions.show', $edition)->with('succes', 'L’édition est enregistrée.');
    }

    public function lancer(Edition $edition, LancerEdition $lancer): RedirectResponse
    {
        $lancer->handle($edition);

        return redirect()->route('editions.show', $edition)->with('succes', "L’édition {$edition->annee} est en cours.");
    }

    /**
     * Formulaire de l'édition : ses paramètres et, tant qu'ils sont modifiables, ses phases et leurs collèges (R17).
     */
    private function formulaire(Edition $edition): View
    {
        return view('editions.formulaire', [
            'edition' => $edition,
            'organisationModifiable' => ! $edition->exists || $edition->organisationEstModifiable(),
            'organisation' => $this->organisation($edition),
            'colleges' => [
                'DEPARTEMENTAL' => CodePoleElectoral::Departemental->libelle(),
                'STRUCTURES' => CodePoleElectoral::Structures->libelle(),
                Phase::COLLEGE_DGTCP => 'Ensemble de la DGTCP',
            ],
            'decoupages' => collect(Decoupage::cases())->mapWithKeys(fn (Decoupage $d): array => [$d->value => $d->libelle()])->all(),
            'origines' => collect(OrigineCandidate::cases())->mapWithKeys(fn (OrigineCandidate $o): array => [$o->value => $o->libelleCollege()])->all(),
        ]);
    }

    /**
     * Les trois phases possibles, préremplies avec celles de l'édition, ou l'organisation par défaut pour une nouvelle édition.
     *
     * @return array<int, array{active: bool, libelle: string, colleges: array<string, array{decoupage: string, origine: string, nb_elues: int}>}>
     */
    private function organisation(Edition $edition): array
    {
        $base = $edition->exists
            ? $edition->phases->mapWithKeys(fn (Phase $phase): array => [$phase->numero => [
                'libelle' => $phase->libelle,
                'colleges' => $phase->colleges->mapWithKeys(fn ($college): array => [
                    $college->poleElectoral?->code->value ?? Phase::COLLEGE_DGTCP => ['decoupage' => $college->decoupage->value, 'origine' => $college->origine_candidates->value, 'nb_elues' => $college->nb_elues],
                ])->all(),
            ]])->all()
            : Phase::ORGANISATION_PAR_DEFAUT;

        $organisation = [];
        foreach (range(1, Phase::NOMBRE_MAXIMUM) as $numero) {
            $colleges = [];
            foreach (['DEPARTEMENTAL', 'STRUCTURES', Phase::COLLEGE_DGTCP] as $cle) {
                $colleges[$cle] = $base[$numero]['colleges'][$cle] ?? [
                    'decoupage' => self::AUCUN_SCRUTIN,
                    'origine' => $numero === 1 ? OrigineCandidate::Automatique->value : OrigineCandidate::Qualification->value,
                    'nb_elues' => 1,
                ];
            }

            $organisation[$numero] = ['active' => isset($base[$numero]), 'libelle' => $base[$numero]['libelle'] ?? "Phase {$numero}", 'colleges' => $colleges];
        }

        return $organisation;
    }

    /**
     * Phases cochées et collèges qui ont des scrutins, au format attendu par OrganiserPhases ; null si le formulaire ne les présente pas.
     *
     * @return array<int, array{libelle: mixed, colleges: array<string, mixed>}>|null
     */
    private function phases(Request $request): ?array
    {
        if (! $request->has('phases')) {
            return null;
        }

        $phases = [];
        foreach ((array) $request->input('phases') as $numero => $phase) {
            if ((int) $numero !== 1 && empty($phase['active'])) {
                continue;
            }

            $phases[(int) $numero] = [
                'libelle' => $phase['libelle'] ?? null,
                'colleges' => collect($phase['colleges'] ?? [])->reject(fn ($college): bool => ($college['decoupage'] ?? self::AUCUN_SCRUTIN) === self::AUCUN_SCRUTIN)->all(),
            ];
        }

        return $phases;
    }

    /**
     * @return array<string, mixed>
     */
    private function donnees(Request $request): array
    {
        return array_filter(['phases' => $this->phases($request)], fn ($valeur): bool => $valeur !== null) + [
            'annee' => $request->integer('annee'),
            'libelle' => $request->input('libelle'),
            'date_debut' => $request->input('date_debut'),
            'date_fin' => $request->input('date_fin'),
            'date_gel' => $request->input('date_gel'),
            'vote_blanc_autorise' => $request->boolean('vote_blanc_autorise'),
            'vote_pour_soi_autorise' => $request->boolean('vote_pour_soi_autorise'),
            'quorum' => $request->filled('quorum') ? $request->input('quorum') : null,
            'delai_reeligibilite' => $request->filled('delai_reeligibilite') ? $request->input('delai_reeligibilite') : 3,
        ];
    }
}
