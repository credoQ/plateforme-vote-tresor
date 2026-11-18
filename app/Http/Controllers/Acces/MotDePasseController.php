<?php

namespace App\Http\Controllers\Acces;

use App\Actions\Acces\ChangerMotDePasse;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MotDePasseController extends Controller
{
    public function edit(Request $request): View
    {
        return view('acces.mot-de-passe', ['obligatoire' => $request->user()->mot_de_passe_a_changer]);
    }

    public function update(Request $request, ChangerMotDePasse $changer): RedirectResponse
    {
        $changer->handle($request->user(), $request->only(['mot_de_passe_actuel', 'mot_de_passe', 'mot_de_passe_confirmation']));
        $request->session()->regenerate();

        return redirect()->route('espace')->with('succes', 'Votre mot de passe a été changé.');
    }
}
