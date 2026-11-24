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

    public function index(): View
    {
        // La liste et ses filtres sont dans le composant Livewire Editions\ListeEditions.
        return view('editions.index');
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
            'scrutins as scrutins_en_preparation_count' => fn ($s) => $s->where('statut', StatutScrutin::EnPreparation->value),
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
            ->paginate(config('app.lignes_par_page'))
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
            'etatsDesPhases' => $edition->phases->mapWithKeys(fn (Phase $etape): array => [$etape->id => $this->etatDeLaPhase($etape)])->all(),
            'taches' => $phase === null ? [] : $this->tachesDeLaPhase($edition, $phase),
            'bornesPlanification' => $phase === null ? null : $this->bornesPlanification($edition, $phase),
        ]);
    }

    /**
     * État d'avancement d'une phase, affiché dans le parcours des phases : libellé et variante du badge.
     *
     * @return array{libelle: string, badge: string}
     */
    private function etatDeLaPhase(Phase $phase): array
    {
        return match (true) {
            $phase->statut === StatutPhase::Cloturee => ['libelle' => 'Clôturée', 'badge' => 'badge--accent'],
            $phase->statut === StatutPhase::Ouverte => ['libelle' => 'Ouverte', 'badge' => 'badge--succes'],
            $phase->date_debut === null => ['libelle' => 'À planifier', 'badge' => ''],
            $phase->scrutins_count === 0 => ['libelle' => 'Planifiée', 'badge' => 'badge--info'],
            $phase->scrutins_en_preparation_count > 0 => ['libelle' => 'Scrutins générés', 'badge' => 'badge--info'],
            default => ['libelle' => 'Listes arrêtées', 'badge' => 'badge--info'],
        };
    }

    /**
     * Préparation et déroulement de la phase en cinq tâches ordonnées (R17 à R19, R27) : la première tâche non
     * terminée est la tâche courante, qui porte l'action principale.
     *
     * @return list<array{cle: string, icone: string, titre: string, detail: string, faite: bool, courante: bool, action: string|null, libelleAction: string, actionPossible: bool, motif: string|null}>
     */
    private function tachesDeLaPhase(Edition $edition, Phase $phase): array
    {
        $planifiee = $phase->statut === StatutPhase::Planifiee;
        $editionClose = $edition->statut === StatutEdition::Cloturee;
        $listesArretees = $phase->scrutins_count > 0 && $phase->scrutins_en_preparation_count === 0;
        $clotureImpossible = $phase->scrutins_termines_count < $phase->scrutins_count;
        $gel = $edition->date_gel?->format('d/m/Y');

        $taches = [
            [
                'cle' => 'planifier', 'icone' => 'calendar-check', 'titre' => 'Planifier la phase',
                'detail' => $phase->date_debut === null
                    ? 'Choisissez les dates de vote de la phase.'
                    : 'Du '.$phase->date_debut->translatedFormat('l d/m/Y').' au '.$phase->date_fin->translatedFormat('l d/m/Y').' ('.((int) $phase->date_debut->diffInDays($phase->date_fin) + 1).' jours).',
                'faite' => $phase->date_debut !== null,
                'action' => $planifiee && ! $editionClose ? 'planifier' : null,
                'libelleAction' => $phase->date_debut === null ? 'Planifier la phase' : 'Modifier les dates',
                'actionPossible' => true, 'motif' => null,
            ],
            [
                'cle' => 'generer', 'icone' => 'wand-sparkles', 'titre' => 'Générer les scrutins',
                'detail' => $phase->scrutins_count > 0
                    ? "{$phase->scrutins_count} scrutin(s) générés, un par zone de vote."
                    : 'Un scrutin est créé pour chaque zone de vote de la phase, selon ses périmètres.',
                'faite' => $phase->scrutins_count > 0,
                'action' => $planifiee && ! $editionClose && $phase->date_debut !== null && $phase->scrutins_count === 0 ? 'generer' : null,
                'libelleAction' => 'Générer les scrutins', 'actionPossible' => true, 'motif' => null,
            ],
            [
                'cle' => 'arreter', 'icone' => 'lock', 'titre' => 'Arrêter les listes électorales',
                'detail' => match (true) {
                    $listesArretees => "Listes figées pour les {$phase->scrutins_count} scrutin(s), d’après les affectations au {$gel}.",
                    $phase->scrutins_count > 0 => "{$phase->scrutins_en_preparation_count} scrutin(s) aux listes encore modifiables. ".($gel !== null ? "Elles se calculent au {$gel}, date de gel." : 'Le premier arrêt fixe la date de gel de l’édition.'),
                    default => 'Les électeurs et les candidates de chaque scrutin sont figés.',
                },
                'faite' => $listesArretees,
                'action' => $phase->scrutins_en_preparation_count > 0 && $phase->statut !== StatutPhase::Cloturee ? 'arreter' : null,
                'libelleAction' => 'Arrêter les listes', 'actionPossible' => true, 'motif' => null,
            ],
            [
                'cle' => 'ouvrir', 'icone' => 'play', 'titre' => 'Ouvrir la phase',
                'detail' => $planifiee
                    ? 'Chaque scrutin s’ouvrira ensuite automatiquement à sa date-heure d’ouverture.'
                    : 'Phase ouverte : chaque scrutin s’ouvre et se ferme à ses dates-heures.',
                'faite' => ! $planifiee,
                'action' => $planifiee && ! $editionClose ? 'ouvrir' : null,
                'libelleAction' => 'Ouvrir la phase',
                'actionPossible' => $edition->statut === StatutEdition::EnCours,
                'motif' => $edition->statut === StatutEdition::EnCours ? null : 'Possible une fois l’édition lancée.',
            ],
            [
                'cle' => 'cloturer', 'icone' => 'flag', 'titre' => 'Clôturer la phase',
                'detail' => $phase->statut === StatutPhase::Cloturee
                    ? 'Phase clôturée : ses lauréates sont reprises dans la phase suivante.'
                    : ($phase->scrutins_count > 0 ? "{$phase->scrutins_termines_count} scrutin(s) terminé(s) sur {$phase->scrutins_count}. " : '').'Possible quand tous les résultats sont publiés.',
                'faite' => $phase->statut === StatutPhase::Cloturee,
                'action' => $phase->statut === StatutPhase::Ouverte ? 'cloturer' : null,
                'libelleAction' => 'Clôturer la phase',
                'actionPossible' => ! $clotureImpossible,
                'motif' => null,
            ],
        ];

        $courante = collect($taches)->search(fn (array $tache): bool => ! $tache['faite']);

        return array_map(fn (array $tache, int $rang): array => [...$tache, 'courante' => $rang === $courante], $taches, array_keys($taches));
    }

    /**
     * Dates à respecter pour planifier la phase (R17) : pendant l'édition, après la phase précédente et avant la suivante.
     *
     * @return array{debut: string, fin: string, consigne: string}
     */
    private function bornesPlanification(Edition $edition, Phase $phase): array
    {
        $precedente = $edition->phases->firstWhere('numero', $phase->numero - 1);
        $suivante = $edition->phases->firstWhere('numero', $phase->numero + 1);

        $consignes = ['pendant l’édition, du '.$edition->date_debut->format('d/m/Y').' au '.$edition->date_fin->format('d/m/Y')];
        if ($precedente?->date_fin !== null) {
            $consignes[] = 'après la fin de la '.$precedente->libelle.' ('.$precedente->date_fin->format('d/m/Y').')';
        }
        if ($suivante?->date_debut !== null) {
            $consignes[] = 'avant le début de la '.$suivante->libelle.' ('.$suivante->date_debut->format('d/m/Y').')';
        }

        return [
            'debut' => $edition->date_debut->toDateString(),
            'fin' => $edition->date_fin->toDateString(),
            'consigne' => 'La phase se déroule '.implode(', ', $consignes).'.',
        ];
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
        $organisation = $this->organisation($edition);

        return view('editions.formulaire', [
            'edition' => $edition,
            'organisationModifiable' => ! $edition->exists || $edition->organisationEstModifiable(),
            'organisation' => $organisation,
            'phasesAffichees' => $this->phasesAffichees($edition, $organisation),
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
     * Nombre de phases ouvertes dans le formulaire : une seule pour une nouvelle édition, celles de l'édition sinon,
     * ou celles de la saisie renvoyée après une erreur. Les suivantes s'ajoutent par le bouton « Ajouter une phase ».
     *
     * @param  array<int, array{active: bool}>  $organisation
     */
    private function phasesAffichees(Edition $edition, array $organisation): int
    {
        if (old('annee') !== null) {
            $actives = collect((array) old('phases', []))->filter(fn ($phase, $numero): bool => (int) $numero === 1 || ! empty($phase['active']));

            return max(1, (int) $actives->keys()->max());
        }

        return $edition->exists ? max(1, count(array_filter($organisation, fn (array $phase): bool => $phase['active']))) : 1;
    }

    /**
     * Phases retenues et collèges qui ont des scrutins, au format attendu par OrganiserPhases ; null si le formulaire ne les présente pas.
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
            'vote_blanc_autorise' => $request->boolean('vote_blanc_autorise'),
            'vote_pour_soi_autorise' => $request->boolean('vote_pour_soi_autorise'),
            'quorum' => $request->filled('quorum') ? $request->input('quorum') : null,
            'delai_reeligibilite' => $request->filled('delai_reeligibilite') ? $request->input('delai_reeligibilite') : 3,
        ];
    }
}
