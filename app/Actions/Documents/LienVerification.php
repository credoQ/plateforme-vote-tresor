<?php

namespace App\Actions\Documents;

use App\Models\ProcesVerbal;
use App\Models\Scrutin;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\URL;

/**
 * Liens de vérification en ligne portés par le QR code des documents PDF. Ils sont signés avec la clé
 * de l'application : un lien modifié ou fabriqué est refusé. L'adresse publique vient de APP_URL.
 */
class LienVerification
{
    public function procesVerbal(ProcesVerbal $pv): string
    {
        return URL::signedRoute('verification.proces-verbal', ['numero' => $pv->numero]);
    }

    /**
     * @param  'liste'|'resultats'  $type
     * @param  list<int>|null  $perimetre
     */
    public function export(string $type, Scrutin $scrutin, string $empreinte, int $lignes, CarbonInterface $edition, ?array $perimetre = null): string
    {
        return URL::signedRoute('verification.export', array_filter([
            'type' => $type,
            'scrutin' => $scrutin->id,
            'empreinte' => $empreinte,
            'lignes' => $lignes,
            'edition' => $edition->utc()->format('YmdHi'),
            'perimetre' => $perimetre === null ? null : implode('-', $perimetre),
        ], fn ($valeur): bool => $valeur !== null));
    }
}
