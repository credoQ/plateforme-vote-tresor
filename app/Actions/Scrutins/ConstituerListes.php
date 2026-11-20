<?php

namespace App\Actions\Scrutins;

use App\Enums\Editions\StatutPhase;
use App\Enums\Scrutins\OrigineCandidate;
use App\Enums\Scrutins\StatutScrutin;
use App\Models\Affectation;
use App\Models\Agent;
use App\Models\Candidate;
use App\Models\Scrutin;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Constitue, ou actualise tant qu'elles ne sont pas arrêtées, la liste électorale et la liste des candidates d'un scrutin.
 *
 * - Électeurs : agents dont l'affectation à la date de gel est dans la circonscription (R14, R15, R24, R29).
 * - Candidates selon le collège du scrutin (R28) : d'office, toutes les électrices ; par qualification, les lauréates
 *   de la phase précédente pour le même pôle, une fois celle-ci clôturée (R35) ; par nomination, celles saisies par le comité.
 *
 * Une candidate déjà désactivée le reste lors d'une actualisation (R33).
 */
class ConstituerListes
{
    /**
     * @throws ValidationException
     */
    public function handle(Scrutin $scrutin): Scrutin
    {
        if (! $scrutin->listesSontModifiables()) {
            throw ValidationException::withMessages(['scrutin' => "Les listes du scrutin « {$scrutin->libelle} » sont arrêtées et ne peuvent plus être modifiées."]);
        }

        return DB::transaction(function () use ($scrutin): Scrutin {
            $scrutin->loadMissing('phase.edition', 'college', 'structures');
            $gel = $scrutin->phase->edition->date_gel;

            /** @var Collection<int, int> $electeurs agent_id => structure_id à la date de gel */
            $electeurs = Affectation::enCoursLe($gel)
                ->whereIn('structure_id', $scrutin->structures->modelKeys())
                ->pluck('structure_id', 'agent_id');

            $this->synchroniserInscriptions($scrutin, $electeurs);

            $candidates = $this->candidatesAttendues($scrutin, $electeurs);
            if ($candidates !== null) {
                $this->synchroniserCandidates($scrutin, $candidates);
            }

            return $scrutin->loadCount(['inscriptions', 'candidates']);
        });
    }

    /**
     * @param  Collection<int, int>  $electeurs
     */
    private function synchroniserInscriptions(Scrutin $scrutin, Collection $electeurs): void
    {
        $scrutin->inscriptions()->whereNotIn('agent_id', $electeurs->keys())->delete();

        $lignes = $electeurs->map(fn (int $structureId, int $agentId): array => [
            'scrutin_id' => $scrutin->id,
            'phase_id' => $scrutin->phase_id,
            'agent_id' => $agentId,
            'structure_id' => $structureId,
            'created_at' => now(),
            'updated_at' => now(),
        ])->values();

        foreach ($lignes->chunk(500) as $paquet) {
            DB::table('inscriptions')->upsert($paquet->all(), ['scrutin_id', 'agent_id'], ['structure_id', 'updated_at']);
        }
    }

    /**
     * Candidates attendues : agent_id => candidate source (null en phase 1). Null si elles ne sont pas encore connues.
     *
     * @param  Collection<int, int>  $electeurs
     * @return Collection<int, int|null>|null
     */
    private function candidatesAttendues(Scrutin $scrutin, Collection $electeurs): ?Collection
    {
        $origine = $scrutin->college->origine_candidates;

        // D'office : toutes les électrices, sauf les femmes modèles encore inéligibles (R59), qui restent électrices.
        if ($origine === OrigineCandidate::Automatique) {
            return Agent::femmes()
                ->whereKey($electeurs->keys())
                ->whereNotIn('matricule', $scrutin->phase->edition->ineligibles()->keys())
                ->pluck('id')
                ->mapWithKeys(fn (int $id): array => [$id => null]);
        }

        // Nomination : les candidates sont saisies par le comité (R58) ; seules restent celles encore inscrites.
        if ($origine === OrigineCandidate::Nomination) {
            $scrutin->candidates()->whereNotIn('agent_id', $electeurs->keys())->delete();

            return null;
        }

        $precedente = $scrutin->phase->phasePrecedente();
        if ($precedente?->statut !== StatutPhase::Cloturee) {
            return null;
        }

        return Candidate::query()
            ->where('est_laureate', true)
            ->actives()
            ->whereHas('scrutin', fn ($query) => $query
                ->where('phase_id', $precedente->id)
                ->where('statut', StatutScrutin::ResultatsPublies->value)
                ->when($scrutin->pole_electoral_id !== null, fn ($q) => $q->where('pole_electoral_id', $scrutin->pole_electoral_id)))
            ->pluck('id', 'agent_id');
    }

    /**
     * @param  Collection<int, int|null>  $attendues
     */
    private function synchroniserCandidates(Scrutin $scrutin, Collection $attendues): void
    {
        $scrutin->candidates()->whereNotIn('agent_id', $attendues->keys())->delete();
        $existantes = $scrutin->candidates()->pluck('agent_id')->flip();

        foreach ($attendues as $agentId => $sourceId) {
            if ($existantes->has($agentId)) {
                continue;
            }

            $scrutin->candidates()->create([
                'agent_id' => $agentId,
                'origine' => $sourceId === null ? OrigineCandidate::Automatique : OrigineCandidate::Qualification,
                'candidate_source_id' => $sourceId,
            ]);
        }
    }
}
