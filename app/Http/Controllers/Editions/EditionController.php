<?php

namespace App\Http\Controllers\Editions;

use App\Actions\Editions\EnregistrerEdition;
use App\Actions\Editions\LancerEdition;
use App\Enums\Editions\Decoupage;
use App\Enums\Referentiel\CodePoleElectoral;
use App\Enums\Scrutins\OrigineCandidate;
use App\Http\Controllers\Controller;
use App\Models\Edition;
use App\Models\Phase;
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

    public function index(): View
    {
        return view('editions.index', [
            'editions' => Edition::withCount('scrutins')->orderByDesc('annee')->get(),
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

    public function show(Edition $edition): View
    {
        $edition->load(['phases.colleges.poleElectoral', 'phases.scrutins' => fn ($q) => $q->withCount([
            'inscriptions',
            'candidates as candidates_actives_count' => fn ($c) => $c->actives(),
        ])->orderBy('code')]);

        return view('editions.show', ['edition' => $edition]);
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
