<?php

namespace App\Http\Controllers\Resultats;

use App\Actions\Resultats\DepouillerScrutin;
use App\Actions\Resultats\EtablirPalmares;
use App\Actions\Resultats\ProlongerScrutin;
use App\Actions\Resultats\PublierResultats;
use App\Actions\Resultats\TrancherEgalite;
use App\Actions\Resultats\ValiderResultats;
use App\Actions\Scrutins\FermerScrutin;
use App\Enums\Editions\StatutEdition;
use App\Enums\Scrutins\StatutScrutin;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Scrutins\ScrutinController;
use App\Jobs\Resultats\GenererFichierProcesVerbal;
use App\Models\Candidate;
use App\Models\Edition;
use App\Models\Scrutin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Écrans du comité pour le dépouillement et les résultats (module 07), procès-verbal et palmarès.
 */
class ResultatController extends Controller
{
    public function fermer(Scrutin $scrutin, FermerScrutin $fermer): RedirectResponse
    {
        $fermer->handle($scrutin);

        return $this->retour($scrutin, 'Le scrutin est fermé : il peut être dépouillé.');
    }

    public function depouiller(Scrutin $scrutin, DepouillerScrutin $depouiller): RedirectResponse
    {
        $pv = $depouiller->handle($scrutin);

        return $this->retour($scrutin, $pv->egalite_a_departager
            ? 'Le scrutin est dépouillé. Une égalité reste à départager avant la validation.'
            : 'Le scrutin est dépouillé. Vérifiez les résultats avant de les valider.');
    }

    public function trancher(Request $request, Scrutin $scrutin, TrancherEgalite $trancher): RedirectResponse
    {
        $retenues = Candidate::where('scrutin_id', $scrutin->id)->whereKey((array) $request->input('retenues', []))->get()->all();
        $trancher->handle($scrutin, $retenues, (string) $request->input('motif', ''));

        return $this->retour($scrutin, 'L’égalité est départagée ; le motif figurera au procès-verbal.');
    }

    public function prolonger(Request $request, Scrutin $scrutin, ProlongerScrutin $prolonger): RedirectResponse
    {
        $donnees = $request->validate(['fermeture' => ['required', 'date_format:Y-m-d\TH:i']], ['required' => 'Indiquez la nouvelle fermeture.', 'date_format' => 'Date et heure invalides.']);
        $prolonger->handle($scrutin, Carbon::createFromFormat('Y-m-d\TH:i', $donnees['fermeture'], ScrutinController::FUSEAU)->utc());

        return $this->retour($scrutin, 'Le scrutin est rouvert jusqu’à la nouvelle fermeture.');
    }

    public function valider(Request $request, Scrutin $scrutin, ValiderResultats $valider): RedirectResponse
    {
        $pv = $valider->handle($scrutin, $request->user());

        return $this->retour($scrutin, "Les résultats sont validés : procès-verbal n° {$pv->numero}.");
    }

    public function publier(Scrutin $scrutin, PublierResultats $publier): RedirectResponse
    {
        $publier->handle($scrutin);

        return $this->retour($scrutin, 'Les résultats sont publiés et archivés.');
    }

    /**
     * Relance la génération du fichier d'un procès-verbal validé mais non généré (tâche en échec ou supprimée).
     */
    public function regenerer(Scrutin $scrutin): RedirectResponse
    {
        $pv = $scrutin->procesVerbal;
        abort_unless($scrutin->statut === StatutScrutin::ResultatsValides && $pv !== null && $pv->fichier === null, 404);

        GenererFichierProcesVerbal::dispatch($pv->id);

        return $this->retour($scrutin, 'La génération du procès-verbal est relancée.');
    }

    /**
     * Procès-verbal : comité et observateur dès la validation, tous les utilisateurs après la publication (R51).
     */
    public function procesVerbal(Request $request, Scrutin $scrutin): Response
    {
        $pv = $scrutin->procesVerbal;
        abort_if($pv?->fichier === null, 404);
        abort_unless($scrutin->statut === StatutScrutin::ResultatsPublies || $request->user()->can('piloter') || $request->user()->can('observer'), 403);

        if ($request->query('format') === 'pdf') {
            return response(Storage::disk('local')->get($pv->fichier), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.$pv->numero.'.pdf"',
            ]);
        }

        $html = Storage::disk('local')->get(preg_replace('/\.pdf$/', '.html', $pv->fichier));
        abort_if($html === null, 404);

        return response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    /**
     * Palmarès de l'édition en cours ou de la dernière édition (R53), à partir des seuls résultats publiés.
     */
    public function palmares(EtablirPalmares $etablir, ?Edition $edition = null): View
    {
        $edition ??= Edition::where('statut', StatutEdition::EnCours->value)->first() ?? Edition::orderByDesc('annee')->first();

        return view('resultats.palmares', [
            'edition' => $edition,
            'palmares' => $edition === null ? null : $etablir->handle($edition),
        ]);
    }

    private function retour(Scrutin $scrutin, string $message): RedirectResponse
    {
        return redirect()->to(route('scrutins.show', $scrutin).'#resultats')->with('succes', $message);
    }
}
