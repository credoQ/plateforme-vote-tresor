<?php

namespace App\Http\Controllers\Editions;

use App\Actions\Editions\CloturerPhase;
use App\Actions\Editions\OuvrirPhase;
use App\Actions\Editions\ParametrerPhase;
use App\Actions\Scrutins\ArreterListes;
use App\Actions\Scrutins\PlanifierScrutinsDeLaPhase;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Scrutins\ScrutinController;
use App\Models\Phase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Planification, arrêt des listes, ouverture et clôture des phases par le comité (R17, R18, R27, R52).
 */
class PhaseController extends Controller
{
    public function update(Request $request, Phase $phase, ParametrerPhase $parametrer): RedirectResponse
    {
        $parametrer->handle($phase, $request->only(['date_debut', 'date_fin']));

        return $this->retour($phase, "La {$phase->libelle} est planifiée.");
    }

    public function ouvrir(Phase $phase, OuvrirPhase $ouvrir): RedirectResponse
    {
        $ouvrir->handle($phase);

        return $this->retour($phase, "La {$phase->libelle} est ouverte : ses scrutins s’ouvriront à leur date-heure.");
    }

    public function cloturer(Phase $phase, CloturerPhase $cloturer): RedirectResponse
    {
        $cloturer->handle($phase);

        return $this->retour($phase, "La {$phase->libelle} est clôturée.");
    }

    /**
     * Arrête les listes de tous les scrutins en préparation de la phase ; le premier arrêt de l'édition fixe sa date de gel (R14, R27).
     */
    public function arreterListes(Phase $phase, ArreterListes $arreter): RedirectResponse
    {
        try {
            $scrutins = $arreter->handle($phase);
        } catch (ValidationException $exception) {
            return redirect()->route('editions.show', [$phase->edition, 'phase' => $phase->numero])->withErrors($exception->errors());
        }

        $gel = $phase->edition->fresh()->date_gel;

        return $this->retour($phase, "Les listes de {$scrutins->count()} scrutin(s) sont arrêtées, d’après les affectations au {$gel->format('d/m/Y')}, date de gel de l’édition.");
    }

    /**
     * Mêmes horaires pour tous les scrutins planifiables de la phase, saisis à l'heure du Bénin (R19).
     */
    public function planifierScrutins(Request $request, Phase $phase, PlanifierScrutinsDeLaPhase $planifier): RedirectResponse
    {
        $donnees = $request->validate([
            'ouverture' => ['required', 'date_format:Y-m-d\TH:i'],
            'fermeture' => ['required', 'date_format:Y-m-d\TH:i'],
        ], ['required' => 'Indiquez la date et l’heure.', 'date_format' => 'Date et heure invalides.']);

        $nombre = $planifier->handle(
            $phase,
            Carbon::createFromFormat('Y-m-d\TH:i', $donnees['ouverture'], ScrutinController::FUSEAU)->utc(),
            Carbon::createFromFormat('Y-m-d\TH:i', $donnees['fermeture'], ScrutinController::FUSEAU)->utc(),
        );

        return $this->retour($phase, "{$nombre} scrutin(s) planifié(s) : chacun reste modifiable depuis sa fiche.");
    }

    private function retour(Phase $phase, string $message): RedirectResponse
    {
        return redirect()->route('editions.show', [$phase->edition, 'phase' => $phase->numero])->with('succes', $message);
    }
}
