<?php

namespace App\Http\Controllers;

use App\Enums\Editions\StatutEdition;
use App\Enums\Editions\StatutPhase;
use App\Enums\Scrutins\StatutScrutin;
use App\Models\Candidate;
use App\Models\Edition;
use App\Models\Inscription;
use App\Models\Phase;
use App\Models\Scrutin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Espace électeur : son scrutin de la phase ouverte, ses candidatures, sa qualification et les résultats publiés.
 * Un compte de gestion sans fiche agent est renvoyé vers la console de gestion.
 */
class EspaceController extends Controller
{
    public function __invoke(Request $request): View|RedirectResponse
    {
        $utilisateur = $request->user()->load(['roles', 'agent', 'structure']);

        // Un compte de gestion sans fiche agent (administrateur technique, observateur externe) ne vote pas.
        if ($utilisateur->agent_id === null && $utilisateur->aAccesALaGestion()) {
            return redirect()->route('tableau-de-bord');
        }

        $inscription = $utilisateur->agent_id === null ? null : Inscription::query()
            ->with('scrutin.phase.edition')
            ->where('agent_id', $utilisateur->agent_id)
            ->whereHas('scrutin.phase', fn ($phase) => $phase->where('statut', StatutPhase::Ouverte->value))
            ->first();

        // Annonces de la plateforme (module 08) : résultats publiés de l'édition en cours, avec leur PDF.
        $resultatsPublies = Scrutin::query()
            ->with(['phase', 'procesVerbal'])
            ->whereHas('phase.edition', fn ($edition) => $edition->where('statut', StatutEdition::EnCours->value))
            ->where('statut', StatutScrutin::ResultatsPublies->value)
            ->orderByDesc('updated_at')
            ->limit(6)
            ->get();

        // Candidatures de l'agent qui acceptent encore un désistement ou une réactivation (R32 à R34).
        $candidatures = $utilisateur->agent_id === null ? collect() : Candidate::query()
            ->with('scrutin.phase')
            ->where('agent_id', $utilisateur->agent_id)
            ->whereHas('scrutin', fn ($scrutin) => $scrutin
                ->whereIn('statut', [StatutScrutin::EnPreparation->value, StatutScrutin::ListesArretees->value])
                ->whereHas('phase.edition', fn ($edition) => $edition->where('statut', StatutEdition::EnCours->value)))
            ->get();

        $scrutin = $inscription?->scrutin;

        return view('espace.accueil', [
            'prenom' => $utilisateur->agent ? Str::of($utilisateur->agent->prenoms)->before(' ')->toString() : $utilisateur->name,
            'affectation' => $utilisateur->agent?->affectationLe(),
            'qualification' => $utilisateur->agent_id === null ? null : $this->qualification($utilisateur->agent_id),
            'femmeModele' => $this->femmeModele($utilisateur->agent_id),
            'inscription' => $inscription,
            'scrutin' => $scrutin,
            'peutVoter' => $scrutin !== null && ! $inscription->a_vote && $scrutin->statut === StatutScrutin::Ouvert && $utilisateur->can('voter'),
            'nbCandidates' => $scrutin?->statut === StatutScrutin::Ouvert ? $scrutin->candidates()->actives()->count() : null,
            'resultatsPublies' => $resultatsPublies,
            'candidatures' => $candidatures,
        ]);
    }

    /**
     * Année de l'édition en cours si l'agent en est la Femme modèle du Trésor (R53), sinon null.
     */
    private function femmeModele(?int $agentId): ?int
    {
        $edition = $agentId === null ? null : Edition::enCours()->first();

        return $edition !== null && $edition->femmeModele()?->agent_id === $agentId ? $edition->annee : null;
    }

    /**
     * Dernière qualification de l'agent dans l'édition en cours (R35, R52), tant que la phase pour laquelle
     * elle est qualifiée n'est pas clôturée : la plateforme l'affiche aussi à celles qui n'ont pas d'adresse e-mail.
     *
     * @return array{scrutin: string, rang: int, phase: string}|null
     */
    private function qualification(int $agentId): ?array
    {
        $laureate = Candidate::query()
            ->with('scrutin.phase')
            ->where('agent_id', $agentId)
            ->where('est_laureate', true)
            ->actives()
            ->whereHas('scrutin', fn ($scrutin) => $scrutin
                ->where('statut', StatutScrutin::ResultatsPublies->value)
                ->whereHas('phase', fn ($phase) => $phase
                    ->where('numero', '<', Phase::FINALE)
                    ->whereHas('edition', fn ($edition) => $edition->where('statut', StatutEdition::EnCours->value))))
            ->get()
            ->sortByDesc(fn (Candidate $candidate): int => $candidate->scrutin->phase->numero)
            ->first();

        $phaseSuivante = $laureate?->scrutin->phase->phaseSuivante();

        if ($phaseSuivante === null || $phaseSuivante->statut === StatutPhase::Cloturee) {
            return null;
        }

        return ['scrutin' => $laureate->scrutin->libelle, 'rang' => $laureate->rang, 'phase' => $phaseSuivante->libelle];
    }
}
