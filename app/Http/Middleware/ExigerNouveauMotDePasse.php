<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tant que le mot de passe provisoire n'est pas changé, seul l'écran de changement est accessible.
 */
class ExigerNouveauMotDePasse
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->mot_de_passe_a_changer && ! $request->routeIs('mot-de-passe.*', 'deconnexion')) {
            return redirect()->route('mot-de-passe.edit');
        }

        return $next($request);
    }
}
