<?php

namespace App\Http\Controllers\Editions;

use App\Actions\Editions\LancerEdition;
use App\Enums\Editions\StatutEdition;
use App\Enums\Editions\StatutPhase;
use App\Enums\Scrutins\StatutScrutin;
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
    public function index(): View
    {
        // La liste et ses filtres sont dans le composant Livewire Editions\ListeEditions.
        return view('editions.index');
    }

    /**
     * Formulaire de création : l'envoi passe par le composant Livewire Editions\FormulaireEdition.
     */
    public function create(): View
    {
        return view('editions.formulaire', ['edition' => new Edition]);
    }

    /**
     * Fiche de l'édition : frise des phases, puis la phase choisie (?phase=) avec ses tâches ; ses scrutins, ses
     * collèges et ses résultats à valider sont dans le composant Livewire Editions\ScrutinsDeLaPhase.
     * Par défaut, la phase ouverte, sinon la première non clôturée.
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

        return view('editions.show', [
            'edition' => $edition,
            'phase' => $phase,
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
        return view('editions.formulaire', ['edition' => $edition]);
    }

    public function lancer(Edition $edition, LancerEdition $lancer): RedirectResponse
    {
        $lancer->handle($edition);

        return redirect()->route('editions.show', $edition)->with('succes', "L’édition {$edition->annee} est en cours.");
    }
}
