<?php

namespace App\Http\Controllers\Suivi;

use App\Actions\Audit\Journaliser;
use App\Enums\Audit\ActionAuditee;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Tâches de la file d'attente en échec (génération des procès-verbaux, envoi des e-mails) : consultation,
 * relance et suppression par l'administrateur. Chaque relance et chaque suppression est journalisée (R56).
 */
class TacheEchoueeController extends Controller
{
    private const FUSEAU = 'Africa/Porto-Novo';

    public function __construct(private Journaliser $journal) {}

    public function index(Request $request): View
    {
        $recherche = trim((string) $request->query('recherche', ''));
        $file = (string) $request->query('file', '');
        $jour = $request->date('jour');

        $taches = DB::table('failed_jobs')
            ->when($recherche !== '', fn ($q) => $q->where(fn ($r) => $r->whereLike('payload', "%{$recherche}%")->orWhereLike('exception', "%{$recherche}%")))
            ->when($file !== '', fn ($q) => $q->where('queue', $file))
            ->when($jour !== null, fn ($q) => $q->whereBetween('failed_at', [
                $jour->copy()->shiftTimezone(self::FUSEAU)->startOfDay()->utc(),
                $jour->copy()->shiftTimezone(self::FUSEAU)->endOfDay()->utc(),
            ]))
            ->orderByDesc('failed_at')
            ->paginate(25)
            ->through(function (object $ligne): object {
                $charge = json_decode($ligne->payload, true) ?? [];

                return (object) [
                    'uuid' => $ligne->uuid,
                    'tache' => class_basename($charge['displayName'] ?? 'Tâche inconnue'),
                    'file' => $ligne->queue,
                    'tentatives' => $charge['attempts'] ?? null,
                    'echec' => Carbon::parse($ligne->failed_at),
                    'erreur' => Str::of($ligne->exception)->before("\n")->limit(220)->toString(),
                    'trace' => Str::limit($ligne->exception, 4000),
                ];
            })
            ->withQueryString();

        return view('suivi.taches-echouees', [
            'taches' => $taches,
            'totalEchecs' => DB::table('failed_jobs')->count(),
            'files' => DB::table('failed_jobs')->distinct()->orderBy('queue')->pluck('queue'),
            'recherche' => $recherche,
            'file' => $file,
            'jour' => $jour?->format('Y-m-d'),
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
