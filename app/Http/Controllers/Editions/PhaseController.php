<?php

namespace App\Http\Controllers\Editions;

use App\Actions\Editions\CloturerPhase;
use App\Actions\Editions\OuvrirPhase;
use App\Actions\Editions\ParametrerPhase;
use App\Http\Controllers\Controller;
use App\Models\Phase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Planification, ouverture et clôture des phases par le comité (R17, R18, R52).
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

    private function retour(Phase $phase, string $message): RedirectResponse
    {
        return redirect()->route('editions.show', [$phase->edition_id, 'phase' => $phase->numero])->with('succes', $message);
    }
}
