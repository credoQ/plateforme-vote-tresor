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
     * Empreinte du dernier bulletin de la chaîne du scrutin, gardée sur la ligne du scrutin : la lecture ne dépend
     * pas du nombre de bulletins déjà déposés.
     */
    public function derniere(int $scrutinId): ?string
    {
        return DB::table('scrutins')->where('id', $scrutinId)->value('derniere_empreinte');
    }

    /**
     * Fait avancer la fin de chaîne du scrutin, dans la transaction du vote, sous le verrou du scrutin.
     * Écriture directe, sans événement ni updated_at : l'heure exacte du dernier vote ne doit être gardée nulle part (R43).
     * Si la fin de chaîne était périmée, l'index bulletins_chaine_sans_embranchement refuse le bulletin : le vote échoue
     * sans casser la chaîne.
     */
    public function avancer(int $scrutinId, string $empreinte): void
    {
        DB::table('scrutins')->where('id', $scrutinId)->update(['derniere_empreinte' => $empreinte]);
    }

    /**
     * Vérifie que tous les bulletins du scrutin forment une seule chaîne intacte (R44), qui se termine sur
     * la fin de chaîne gardée par le scrutin (un bulletin retiré en fin de chaîne est ainsi détecté).
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

        return $parcourus === $bulletins->count()
            && $parcourus === Bulletin::where('scrutin_id', $scrutinId)->count()
            && $precedente === ($this->derniere($scrutinId) ?? 'DEBUT');
    }
}
