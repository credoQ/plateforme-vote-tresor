<?php

namespace App\Actions\Documents;

use App\Models\Scrutin;

/**
 * Contenu des exports (liste électorale, résultats) et son empreinte, recalculable pour la vérification en ligne.
 */
class LignesExport
{
    /**
     * Liste électorale d'un scrutin, limitée au périmètre d'un point focal le cas échéant. Sans l'émargement.
     *
     * @param  list<int>|null  $perimetre
     * @return array{entete: list<string>, lignes: list<list<string|int|float>>}
     */
    public function listeElectorale(Scrutin $scrutin, ?array $perimetre = null): array
    {
        $lignes = $scrutin->inscriptions()
            ->with(['agent', 'structure'])
            ->when($perimetre !== null, fn ($q) => $q->whereIn('inscriptions.structure_id', $perimetre))
            ->join('agents', 'agents.id', '=', 'inscriptions.agent_id')
            ->orderBy('agents.nom')->orderBy('agents.prenoms')->orderBy('agents.matricule')
            ->select('inscriptions.*')
            ->get()
            ->map(fn ($inscription): array => [
                $inscription->agent->matricule, $inscription->agent->nom, $inscription->agent->prenoms,
                $inscription->agent->sexe->value, $inscription->structure->code,
            ])
            ->all();

        return ['entete' => ['Matricule', 'Nom', 'Prénoms', 'Sexe', 'Structure'], 'lignes' => $lignes];
    }

    /**
     * Résultats d'un scrutin, par rang.
     *
     * @return array{entete: list<string>, lignes: list<list<string|int|float>>}
     */
    public function resultats(Scrutin $scrutin): array
    {
        $lignes = $scrutin->candidates()->with('agent')->orderByRaw('rang nulls last')->orderBy('id')->get()
            ->map(fn ($candidate): array => [
                $candidate->rang ?? '', $candidate->agent->matricule, $candidate->agent->nomComplet(),
                $candidate->nb_voix ?? '', $candidate->pourcentage === null ? '' : (float) $candidate->pourcentage,
                $candidate->est_laureate ? 'Lauréate' : '', $candidate->statut->libelle(),
            ])
            ->all();

        return ['entete' => ['Rang', 'Matricule', 'Candidate', 'Voix', '%', 'Résultat', 'Statut'], 'lignes' => $lignes];
    }

    /**
     * Empreinte du contenu : SHA-256 des lignes, tronqué à 32 caractères.
     *
     * @param  list<list<string|int|float>>  $lignes
     */
    public function empreinte(array $lignes): string
    {
        return substr(hash('sha256', json_encode($lignes, JSON_UNESCAPED_UNICODE)), 0, 32);
    }
}
