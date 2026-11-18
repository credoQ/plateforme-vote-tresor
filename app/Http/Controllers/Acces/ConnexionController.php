<?php

namespace App\Http\Controllers\Acces;

use App\Actions\Audit\Journaliser;
use App\Enums\Audit\ActionAuditee;
use App\Http\Controllers\Controller;
use App\Http\Requests\Acces\ConnexionRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ConnexionController extends Controller
{
    public function create(): View
    {
        return view('acces.connexion');
    }

    public function store(ConnexionRequest $request, Journaliser $journal): RedirectResponse
    {
        $request->authentifier();
        $request->session()->regenerate();
        $request->user()->forceFill(['derniere_connexion' => now()])->saveQuietly();
        $journal->handle(ActionAuditee::Connexion, $request->user());

        return redirect()->intended(route('espace'));
    }

    public function destroy(Request $request, Journaliser $journal): RedirectResponse
    {
        $journal->handle(ActionAuditee::Deconnexion, $request->user());
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('connexion');
    }
}
