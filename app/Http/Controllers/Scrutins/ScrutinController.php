<?php

namespace App\Http\Controllers\Scrutins;

use App\Actions\Scrutins\GenererScrutins;
use App\Actions\Scrutins\PlanifierScrutin;
use App\Actions\Scrutins\RouvrirListes;
use App\Enums\Acces\CodeRole;
use App\Enums\Scrutins\StatutScrutin;
use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Models\Phase;
use App\Models\Scrutin;
use App\Models\Structure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    public function generer(Phase $phase, GenererScrutins $generer): RedirectResponse
    {
        $rapport = $generer->handle($phase);

        return redirect()->route('editions.show', [$phase->edition, 'phase' => $phase->numero])
            ->with('succes', "{$rapport->scrutins->count()} scrutin(s) générés pour la {$phase->libelle}.")
            ->with('sans_femme', $rapport->circonscriptionsSansFemme)
            ->with('sans_agent', $rapport->circonscriptionsSansAgent);
    }

    /**
     * Fiche d'un scrutin : déroulement en étapes, étape en cours (comité) et chiffres clés. Le contenu des onglets
     * (candidates, liste électorale, zone de vote, résultats) est dans le composant Livewire Scrutins\ContenuDuScrutin.
     */
    public function show(Request $request, Scrutin $scrutin): View
    {
        $utilisateur = $request->user();
        abort_unless($utilisateur->can('observer') || $utilisateur->can('controler-listes'), 403);
        $perimetre = $utilisateur->aLeRole(CodeRole::Comite, CodeRole::Observateur, CodeRole::Administrateur) ? null : $utilisateur->perimetre();

        $scrutin->load(['phase.edition', 'college.poleElectoral', 'poleElectoral', 'procesVerbal']);

        $structuresInscription = $scrutin->inscriptions()->pluck('structure_id', 'agent_id');
        $candidates = $scrutin->candidates()->get()
            ->filter(fn (Candidate $c) => $perimetre === null || in_array($structuresInscription[$c->agent_id] ?? null, $perimetre, true))
            ->values();
        $deroulement = $this->deroulement($scrutin);

        return view('scrutins.show', [
            'scrutin' => $scrutin,
            'candidates' => $candidates,
            'actives' => $candidates->filter->estActive(),
            'pv' => $scrutin->procesVerbal,
            'restreint' => $perimetre !== null,
            'fuseau' => self::FUSEAU,
            'pilote' => $utilisateur->can('piloter'),
            'participation' => $scrutin->participation(),
            'deroulement' => $deroulement,
            'rappels' => $this->rappels($scrutin, $deroulement['courante'], $candidates),
            'consignePlanification' => $scrutin->phase->date_debut === null
                ? 'La phase n’est pas encore planifiée.'
                : "Le vote se déroule pendant la {$scrutin->phase->libelle}, du {$scrutin->phase->date_debut->format('d/m/Y')} au {$scrutin->phase->date_fin->format('d/m/Y')}.",
        ]);
    }

    /**
     * Étapes terminées rappelées sous l'étape courante, avec l'action encore possible (réouverture des listes,
     * modification des horaires).
     *
     * @param  Collection<int, Candidate>  $candidates
     * @return list<array{texte: string, action: string|null}>
     */
    private function rappels(Scrutin $scrutin, ?string $courante, Collection $candidates): array
    {
        $rappels = [];

        if ($scrutin->statut !== StatutScrutin::EnPreparation && $courante !== 'listes') {
            $rappels[] = [
                'texte' => 'Listes arrêtées : '.$scrutin->inscriptions()->count().' inscrit(s), '.$candidates->filter->estActive()->count().' candidate(s) active(s)',
                'action' => $scrutin->listesPeuventEtreRouvertes() ? 'rouvrir' : null,
            ];
        }

        if ($scrutin->date_heure_ouverture !== null && ! in_array($courante, ['horaires', 'vote'], true)) {
            $rappels[] = [
                'texte' => 'Vote du '.$scrutin->date_heure_ouverture->timezone(self::FUSEAU)->format('d/m/Y à H:i').' au '.$scrutin->date_heure_fermeture->timezone(self::FUSEAU)->format('d/m/Y à H:i'),
                'action' => $scrutin->accepteLesDesistements() ? 'planifier' : null,
            ];
        }

        return $rappels;
    }

    /**
     * Déroulement du scrutin en cinq étapes (R19, R27, R39, R47 à R51) : listes, horaires, vote, dépouillement et
     * résultats. La première étape non terminée est l'étape courante ; les étapes terminées sont rappelées avec
     * leurs actions encore possibles.
     *
     * @return array{etapes: list<array{cle: string, titre: string, detail: string, faite: bool, courante: bool}>, courante: string|null, annule: bool}
     */
    private function deroulement(Scrutin $scrutin): array
    {
        $statut = $scrutin->statut;
        $ouverture = $scrutin->date_heure_ouverture?->timezone(self::FUSEAU);
        $fermeture = $scrutin->date_heure_fermeture?->timezone(self::FUSEAU);
        $voteTermine = in_array($statut, [StatutScrutin::Clos, StatutScrutin::ResultatsValides, StatutScrutin::ResultatsPublies], true);

        $etapes = [
            ['cle' => 'listes', 'titre' => 'Listes', 'faite' => $statut !== StatutScrutin::EnPreparation,
                'detail' => $statut === StatutScrutin::EnPreparation ? 'En préparation' : 'Arrêtées'],
            ['cle' => 'horaires', 'titre' => 'Horaires', 'faite' => $ouverture !== null,
                'detail' => $ouverture === null ? 'À fixer' : 'Le '.$ouverture->format('d/m à H:i')],
            ['cle' => 'vote', 'titre' => 'Vote', 'faite' => $voteTermine,
                'detail' => match (true) {
                    $statut === StatutScrutin::Ouvert => 'En cours',
                    $statut === StatutScrutin::Annule => 'Annulé',
                    $voteTermine => 'Clos le '.$fermeture?->format('d/m à H:i'),
                    default => 'Ouverture automatique',
                }],
            ['cle' => 'depouillement', 'titre' => 'Dépouillement', 'faite' => $scrutin->procesVerbal !== null,
                'detail' => $scrutin->procesVerbal !== null ? 'Voix comptées' : 'Après la fermeture'],
            ['cle' => 'resultats', 'titre' => 'Résultats', 'faite' => $statut === StatutScrutin::ResultatsPublies,
                'detail' => match ($statut) {
                    StatutScrutin::ResultatsPublies => 'Publiés',
                    StatutScrutin::ResultatsValides => 'Validés, à publier',
                    default => 'Validation, publication',
                }],
        ];

        $courante = $statut === StatutScrutin::Annule ? null : collect($etapes)->first(fn (array $etape): bool => ! $etape['faite'])['cle'] ?? null;

        return [
            'etapes' => array_map(fn (array $etape): array => [...$etape, 'courante' => $etape['cle'] === $courante], $etapes),
            'courante' => $courante,
            'annule' => $statut === StatutScrutin::Annule,
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

        return redirect()->route('scrutins.show', $scrutin)->with('succes', 'Les listes sont rouvertes : corrigez les agents, actualisez, puis arrêtez de nouveau les listes depuis la phase.');
    }
}
