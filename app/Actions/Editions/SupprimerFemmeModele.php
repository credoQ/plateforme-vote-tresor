<?php

namespace App\Actions\Editions;

use App\Actions\Audit\Journaliser;
use App\Enums\Audit\ActionAuditee;
use App\Models\FemmeModele;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Supprime de l'historique une ligne saisie ou importée par erreur (R59). Une femme modèle proclamée par la plateforme reste.
 * La suppression est tracée.
 */
class SupprimerFemmeModele
{
    public function __construct(private Journaliser $journal) {}

    /**
     * @throws ValidationException
     */
    public function handle(FemmeModele $femmeModele): void
    {
        if (! $femmeModele->source->estRetirable()) {
            throw ValidationException::withMessages(['femme_modele' => 'Une femme modèle proclamée par la plateforme ne se supprime pas de l’historique.']);
        }

        DB::transaction(function () use ($femmeModele): void {
            $this->journal->handle(ActionAuditee::FemmeModeleSupprimee, $femmeModele, ['matricule' => $femmeModele->matricule, 'annee' => $femmeModele->annee]);
            $femmeModele->delete();
        });
    }
}
