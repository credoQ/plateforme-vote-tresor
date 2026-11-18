<?php

namespace App\Actions\Agents;

/**
 * Bilan d'un import d'agents : lignes traitées et lignes rejetées avec leurs motifs.
 */
final class RapportImport
{
    public int $agentsCrees = 0;

    public int $agentsMisAJour = 0;

    public int $affectationsCreees = 0;

    /**
     * Motifs de rejet par numéro de ligne du fichier (l'en-tête est la ligne 1).
     *
     * @var array<int, list<string>>
     */
    public array $rejets = [];

    /**
     * @param  list<string>  $motifs
     */
    public function rejeter(int $ligne, array $motifs): void
    {
        $this->rejets[$ligne] = [...($this->rejets[$ligne] ?? []), ...$motifs];
    }

    public function lignesAcceptees(): int
    {
        return $this->agentsCrees + $this->agentsMisAJour;
    }
}
