<?php

namespace App\Http\Controllers\Suivi;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

/**
 * Consultation du journal d'audit (R56) par l'observateur, le comité et l'administrateur, en lecture seule.
 * La liste et ses filtres sont dans le composant Livewire Suivi\JournalAudit.
 */
class JournalAuditController extends Controller
{
    public function __invoke(): View
    {
        return view('suivi.journal');
    }
}
