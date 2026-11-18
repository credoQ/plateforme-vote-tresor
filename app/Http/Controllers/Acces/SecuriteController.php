<?php

namespace App\Http\Controllers\Acces;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Sécurité du compte : changement du mot de passe (obligatoire à la première connexion)
 * et double authentification facultative. Les formulaires sont traités par Fortify.
 */
class SecuriteController extends Controller
{
    public function edit(Request $request): View
    {
        return view('acces.securite', [
            'utilisateur' => $request->user(),
            'obligatoire' => $request->user()->mot_de_passe_a_changer,
        ]);
    }

    /**
     * Activation, confirmation et gestion de la double authentification (TOTP), après confirmation du mot de passe.
     */
    public function doubleAuthentification(Request $request): View
    {
        return view('acces.securite-double-authentification', ['utilisateur' => $request->user()]);
    }
}
