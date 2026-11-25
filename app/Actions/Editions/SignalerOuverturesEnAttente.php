<?php

namespace App\Actions\Editions;

use App\Enums\Editions\StatutEdition;
use App\Enums\Editions\StatutPhase;
use App\Enums\Scrutins\StatutScrutin;
use App\Models\Edition;
use App\Models\Phase;
use App\Models\Scrutin;
use Illuminate\Support\Collection;

/**
 * Avertit le comité quand un scrutin planifié arrive à son heure d'ouverture alors que sa phase n'est pas ouverte
 * (R18, R19) : il ne s'ouvrira pas tant que la phase ne l'est pas. Heure passée : à replanifier avant d'ouvrir la
 * phase (OuvrirPhase le refuse) ; ouverture dans moins de 24 h : la phase reste à ouvrir à temps.
 */
class SignalerOuverturesEnAttente
{
    /**
     * Délai d'avertissement avant l'heure d'ouverture, en heures.
     */
    public const DELAI_HEURES = 24;

    /**
     * Scrutins encore à ouvrir dont l'heure d'ouverture tombe avant $limite.
     *
     * @return Collection<int, Scrutin>
     */
    public static function scrutinsAvant(Phase $phase, \DateTimeInterface $limite): Collection
    {
        return $phase->scrutins()
            ->whereIn('statut', [StatutScrutin::EnPreparation->value, StatutScrutin::ListesArretees->value])
            ->whereNotNull('date_heure_ouverture')
            ->where('date_heure_ouverture', '<=', $limite)
            ->orderBy('date_heure_ouverture')
            ->orderBy('code')
            ->get();
    }

    /**
     * @return list<array{phase: Phase, ton: string, texte: string}>
     */
    public function handle(Edition $edition): array
    {
        if ($edition->statut === StatutEdition::Cloturee) {
            return [];
        }

        $alertes = [];
        foreach ($edition->phases->where('statut', StatutPhase::Planifiee) as $phase) {
            $scrutins = self::scrutinsAvant($phase, now()->addHours(self::DELAI_HEURES));
            [$passes, $proches] = $scrutins->partition(fn (Scrutin $scrutin): bool => $scrutin->date_heure_ouverture->isPast());
            $aFaire = $edition->statut === StatutEdition::EnCours ? 'ouvrir la phase' : 'lancer l’édition puis ouvrir la phase';

            if ($passes->isNotEmpty()) {
                $alertes[] = ['phase' => $phase, 'ton' => 'erreur', 'texte' => "{$phase->libelle} : l’heure d’ouverture de {$passes->count()} scrutin(s) est passée ({$this->codes($passes)}) alors que la phase n’est pas ouverte. Ils ne s’ouvriront pas : replanifiez-les, puis {$aFaire}."];
            }

            if ($proches->isNotEmpty()) {
                $premiere = $proches->first()->date_heure_ouverture->setTimezone(Scrutin::FUSEAU)->format('d/m/Y à H:i');
                $alertes[] = ['phase' => $phase, 'ton' => 'attente', 'texte' => "{$phase->libelle} : {$proches->count()} scrutin(s) doivent s’ouvrir dans moins de ".self::DELAI_HEURES." h (le premier le {$premiere}), mais la phase n’est pas ouverte. Pensez à {$aFaire}."];
            }
        }

        return $alertes;
    }

    /**
     * @param  Collection<int, Scrutin>  $scrutins
     */
    private function codes(Collection $scrutins): string
    {
        return $scrutins->take(5)->pluck('code')->implode(', ').($scrutins->count() > 5 ? '…' : '');
    }
}
