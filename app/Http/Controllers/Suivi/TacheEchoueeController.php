<?php

namespace App\Http\Controllers\Suivi;

use App\Actions\Audit\Journaliser;
use App\Enums\Audit\ActionAuditee;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Tâches de la file d'attente en échec (génération des procès-verbaux, envoi des e-mails) : consultation,
 * relance et suppression par l'administrateur. Chaque relance et chaque suppression est journalisée (R56).
 */
class TacheEchoueeController extends Controller
{
    public function __construct(private Journaliser $journal) {}

    /**
     * Tâches en échec : la liste filtrée est dans le composant Livewire Suivi\ListeTachesEchouees.
     */
    public function index(): View
    {
        return view('suivi.taches-echouees', [
            'totalEchecs' => DB::table('failed_jobs')->count(),
            'enAttente' => DB::table('jobs')->count(),
        ]);
    }

    public function relancer(string $uuid): RedirectResponse
    {
        $tache = $this->tache($uuid);

        DB::transaction(function () use ($uuid, $tache): void {
            Artisan::call('queue:retry', ['id' => [$uuid]]);
            $this->journal->handle(ActionAuditee::TacheRelancee, null, ['uuid' => $uuid, 'tache' => $tache]);
        });

        return redirect()->route('taches-echouees.index')->with('succes', "La tâche {$tache} est remise en file d’attente.");
    }

    public function relancerTout(): RedirectResponse
    {
        $nombre = DB::table('failed_jobs')->count();

        DB::transaction(function () use ($nombre): void {
            Artisan::call('queue:retry', ['id' => ['all']]);
            $this->journal->handle(ActionAuditee::TacheRelancee, null, ['uuid' => 'toutes', 'nombre' => $nombre]);
        });

        return redirect()->route('taches-echouees.index')->with('succes', "{$nombre} tâche(s) remise(s) en file d’attente.");
    }

    public function supprimer(string $uuid): RedirectResponse
    {
        $tache = $this->tache($uuid);

        DB::transaction(function () use ($uuid, $tache): void {
            Artisan::call('queue:forget', ['id' => $uuid]);
            $this->journal->handle(ActionAuditee::TacheSupprimee, null, ['uuid' => $uuid, 'tache' => $tache]);
        });

        return redirect()->route('taches-echouees.index')->with('succes', "La tâche {$tache} est supprimée sans être relancée.");
    }

    private function tache(string $uuid): string
    {
        $ligne = DB::table('failed_jobs')->where('uuid', $uuid)->first();
        abort_if($ligne === null, 404);

        return class_basename(json_decode($ligne->payload, true)['displayName'] ?? 'Tâche inconnue');
    }
}
