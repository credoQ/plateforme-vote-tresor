<?php

namespace App\Actions\Scrutins;

use App\Actions\Audit\Journaliser;
use App\Enums\Audit\ActionAuditee;
use App\Enums\Editions\StatutPhase;
use App\Enums\Scrutins\StatutScrutin;
use App\Models\Edition;
use App\Models\Phase;
use App\Models\Scrutin;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Arrête, pour toute une phase, les listes électorales et les listes des candidates de ses scrutins en préparation
 * (R27, R30) : les listes sont actualisées une dernière fois, puis figées.
 *
 * - Le premier arrêt des listes de l'édition fixe sa date de gel au jour même (R14, R16) : toutes les listes de
 *   l'édition, de toutes ses phases, se calculent ensuite à cette date, et les mutations postérieures n'y changent
 *   rien (R15).
 * - Tout ou rien : si un scrutin a une liste vide ou aucune candidate, aucune liste n'est arrêtée et la date de gel
 *   n'est pas fixée ; le message nomme les scrutins à compléter.
 * - Après une réouverture motivée d'un scrutin (R27), le même arrêt ne reprend que les scrutins rouverts.
 */
class ArreterListes
{
    /**
     * Nombre de scrutins à compléter nommés dans le message de refus.
     */
    private const SCRUTINS_NOMMES = 10;

    public function __construct(private ConstituerListes $constituerListes, private Journaliser $journal) {}

    /**
     * @return Collection<int, Scrutin> les scrutins dont les listes viennent d'être arrêtées
     *
     * @throws ValidationException
     */
    public function handle(Phase $phase): Collection
    {
        return DB::transaction(function () use ($phase): Collection {
            $phase = Phase::lockForUpdate()->findOrFail($phase->id);
            $edition = Edition::lockForUpdate()->findOrFail($phase->edition_id);
            $phase->setRelation('edition', $edition);

            if ($phase->statut === StatutPhase::Cloturee) {
                $this->echouer("La {$phase->libelle} est clôturée : ses listes ne se modifient plus.");
            }

            $scrutins = $phase->scrutins()->where('statut', StatutScrutin::EnPreparation->value)->orderBy('code')->lockForUpdate()->get();
            if ($scrutins->isEmpty()) {
                $this->echouer($phase->scrutins()->exists()
                    ? "Toutes les listes de la {$phase->libelle} sont déjà arrêtées."
                    : "Générez d’abord les scrutins de la {$phase->libelle}.");
            }

            if (! $edition->listesSontGelees()) {
                $edition->update(['date_gel' => Carbon::today()]);
            }

            $scrutins = $scrutins->map(fn (Scrutin $scrutin): Scrutin => $this->constituerListes->handle($scrutin->setRelation('phase', $phase)));
            $this->refuserSiIncomplets($scrutins);

            foreach ($scrutins as $scrutin) {
                $scrutin->update(['statut' => StatutScrutin::ListesArretees]);
                $this->journal->handle(ActionAuditee::ListesArretees, $scrutin, [
                    'scrutin' => $scrutin->code,
                    'inscrits' => $scrutin->inscriptions_count,
                    'candidates' => $scrutin->candidates_count,
                    'date_gel' => $edition->date_gel->toDateString(),
                ]);
            }

            return $scrutins;
        });
    }

    /**
     * Un scrutin sans électeur ou sans candidate bloque l'arrêt de toute la phase ; la transaction est annulée,
     * date de gel comprise.
     *
     * @param  Collection<int, Scrutin>  $scrutins
     *
     * @throws ValidationException
     */
    private function refuserSiIncomplets(Collection $scrutins): void
    {
        $incomplets = $scrutins
            ->map(fn (Scrutin $scrutin): ?string => match (true) {
                $scrutin->inscriptions_count === 0 => "{$scrutin->libelle} : liste électorale vide, aucun agent en service dans la zone de vote.",
                $scrutin->candidates_count === 0 => "{$scrutin->libelle} : aucune candidate (nominez-en au moins une, ou, pour des lauréates qualifiées, clôturez d’abord la phase précédente).",
                default => null,
            })
            ->filter()
            ->values();

        if ($incomplets->isEmpty()) {
            return;
        }

        $messages = ["Aucune liste n’est arrêtée : {$incomplets->count()} scrutin(s) à compléter.", ...$incomplets->take(self::SCRUTINS_NOMMES)];
        if ($incomplets->count() > self::SCRUTINS_NOMMES) {
            $messages[] = 'Et '.($incomplets->count() - self::SCRUTINS_NOMMES).' autre(s).';
        }

        throw ValidationException::withMessages(['listes' => $messages]);
    }

    /**
     * @throws ValidationException
     */
    private function echouer(string $message): never
    {
        throw ValidationException::withMessages(['listes' => $message]);
    }
}
