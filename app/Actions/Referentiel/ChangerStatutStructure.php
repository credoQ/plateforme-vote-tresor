<?php

namespace App\Actions\Referentiel;

use App\Actions\Audit\Journaliser;
use App\Enums\Audit\ActionAuditee;
use App\Models\Affectation;
use App\Models\Structure;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Désactive ou réactive une structure du référentiel (R5 : statut de la structure), avec trace au journal.
 *
 * Une structure désactivée ne reçoit plus d'affectation et n'a plus de scrutin (R10, R20). Pour ne laisser
 * aucun agent ni aucune structure rattachée sans circonscription, la désactivation est refusée tant que la
 * structure a des agents en service ou des structures rattachées actives.
 */
class ChangerStatutStructure
{
    public function __construct(private Journaliser $journal) {}

    /**
     * @throws ValidationException
     */
    public function desactiver(Structure $structure, ?string $motif = null): Structure
    {
        return DB::transaction(function () use ($structure, $motif): Structure {
            $structure = Structure::lockForUpdate()->findOrFail($structure->id);

            if (! $structure->actif) {
                $this->echouer('La structure est déjà désactivée.');
            }

            $agentsEnService = Affectation::enCoursLe()->where('structure_id', $structure->id)->count();
            if ($agentsEnService > 0) {
                $this->echouer("La structure compte {$agentsEnService} agent(s) en service : mutez-les ou mettez fin à leur affectation avant de la désactiver.");
            }

            if ($structure->structuresFilles()->actives()->exists()) {
                $this->echouer('Des structures rattachées sont encore actives : désactivez-les ou rattachez-les ailleurs d’abord.');
            }

            $motif = filled($motif) ? mb_substr(trim($motif), 0, 500) : null;
            $structure->update(['actif' => false]);
            $this->journal->handle(ActionAuditee::StructureDesactivee, $structure, ['code' => $structure->code, 'motif' => $motif]);

            return $structure;
        });
    }

    /**
     * @throws ValidationException
     */
    public function reactiver(Structure $structure): Structure
    {
        return DB::transaction(function () use ($structure): Structure {
            $structure = Structure::lockForUpdate()->findOrFail($structure->id);

            if ($structure->actif) {
                $this->echouer('La structure est déjà active.');
            }

            if ($structure->structure_parente_id !== null && ! Structure::whereKey($structure->structure_parente_id)->value('actif')) {
                $this->echouer('La structure de rattachement est désactivée : réactivez-la d’abord.');
            }

            $structure->update(['actif' => true]);
            $this->journal->handle(ActionAuditee::StructureReactivee, $structure, ['code' => $structure->code]);

            return $structure;
        });
    }

    /**
     * @throws ValidationException
     */
    private function echouer(string $message): never
    {
        throw ValidationException::withMessages(['structure' => $message]);
    }
}
