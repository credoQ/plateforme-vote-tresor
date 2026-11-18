<?php

namespace App\Actions\Vote;

use App\Models\Bulletin;
use Illuminate\Support\Facades\DB;

/**
 * Empreinte chaînée des bulletins (R44).
 *
 * Chaque empreinte est un HMAC-SHA256, calculé avec la clé de l'application, du bulletin et de l'empreinte
 * du bulletin précédent du même scrutin. Toute modification, suppression ou insertion hors de la plateforme
 * casse la chaîne. Changer APP_KEY rend les chaînes existantes invérifiables.
 */
class EmpreinteBulletin
{
    public function calculer(Bulletin $bulletin): string
    {
        return hash_hmac('sha256', implode('|', [
            $bulletin->empreinte_precedente ?? 'DEBUT',
            $bulletin->id,
            $bulletin->scrutin_id,
            $bulletin->est_blanc ? 'BLANC' : $bulletin->candidate_id,
            $bulletin->date_heure->utc()->format('Y-m-d\TH:i:s\Z'),
        ]), (string) config('app.key'));
    }

    /**
     * Empreinte du dernier bulletin de la chaîne du scrutin, celui qu'aucun autre ne suit.
     */
    public function derniere(int $scrutinId): ?string
    {
        return Bulletin::query()
            ->where('scrutin_id', $scrutinId)
            ->whereNotExists(fn ($query) => $query->select(DB::raw(1))
                ->from('bulletins as suivants')
                ->whereColumn('suivants.empreinte_precedente', 'bulletins.empreinte'))
            ->value('empreinte');
    }

    /**
     * Vérifie que tous les bulletins du scrutin forment une seule chaîne intacte (R44).
     */
    public function chaineEstIntacte(int $scrutinId): bool
    {
        $bulletins = Bulletin::where('scrutin_id', $scrutinId)->get()->keyBy(fn (Bulletin $b): string => $b->empreinte_precedente ?? 'DEBUT');

        $parcourus = 0;
        $precedente = 'DEBUT';
        while (($bulletin = $bulletins->get($precedente)) !== null) {
            if (! hash_equals($this->calculer($bulletin), $bulletin->empreinte)) {
                return false;
            }

            $precedente = $bulletin->empreinte;
            $parcourus++;
        }

        return $parcourus === $bulletins->count() && $parcourus === Bulletin::where('scrutin_id', $scrutinId)->count();
    }
}
