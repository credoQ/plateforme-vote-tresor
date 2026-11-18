<?php

namespace App\Http\Controllers\Acces;

use App\Actions\Acces\ReinitialiserMotDePasse;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class ReinitialisationMotDePasseController extends Controller
{
    public function __invoke(User $utilisateur, ReinitialiserMotDePasse $reinitialiser): RedirectResponse
    {
        $motDePasse = $reinitialiser->handle($utilisateur);

        return redirect()->route('utilisateurs.edit', $utilisateur)
            ->with('mot_de_passe_provisoire', $motDePasse)
            ->with('succes', "Le mot de passe de {$utilisateur->login} est réinitialisé.");
    }
}
