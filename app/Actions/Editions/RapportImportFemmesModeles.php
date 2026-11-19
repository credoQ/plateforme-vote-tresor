<?php

namespace App\Actions\Editions;

/**
 * Bilan d'un import de l'historique des femmes modèles : lignes enregistrées et lignes rejetées avec leurs motifs.
 */
final class RapportImportFemmesModeles
{
    public int $enregistrees = 0;

    /**
     * Motifs de rejet par numéro de ligne du fichier (l'en-tête est la ligne 1).
     *
     * @var array<int, string>
     */
    public array $rejets = [];
}
