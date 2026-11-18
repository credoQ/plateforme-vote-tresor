<?php

namespace App\Http\Controllers\Suivi;

use App\Enums\Audit\ActionAuditee;
use App\Http\Controllers\Controller;
use App\Models\JournalAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Consultation du journal d'audit (R56) par l'observateur, le comité et l'administrateur, en lecture seule.
 */
class JournalAuditController extends Controller
{
    public function __invoke(Request $request): View
    {
        $action = ActionAuditee::tryFrom((string) $request->query('action', ''));
        $login = trim((string) $request->query('login', ''));
        $du = $this->date($request->query('du'));
        $au = $this->date($request->query('au'));

        $entrees = JournalAudit::query()
            ->when($action !== null, fn ($q) => $q->where('action', $action->value))
            ->when($login !== '', fn ($q) => $q->whereLike('login', "%{$login}%"))
            ->when($du !== null, fn ($q) => $q->where('date_heure', '>=', $du->startOfDay()))
            ->when($au !== null, fn ($q) => $q->where('date_heure', '<=', $au->endOfDay()))
            ->orderByDesc('date_heure')
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        return view('suivi.journal', [
            'entrees' => $entrees,
            'action' => $action,
            'login' => $login,
            'du' => $du?->toDateString(),
            'au' => $au?->toDateString(),
        ]);
    }

    private function date(mixed $valeur): ?Carbon
    {
        if (! is_string($valeur) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $valeur) !== 1) {
            return null;
        }

        return Carbon::createFromFormat('!Y-m-d', $valeur, 'Africa/Porto-Novo')->utc();
    }
}
