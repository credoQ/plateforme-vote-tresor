<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Formulaire d'action de la console envoyé sans recharger la page (resources/js/app.js) : la redirection qui suit
 * l'action devient une réponse JSON portant l'adresse cible, que le navigateur ouvre par la navigation Livewire.
 * Le message de succès et les erreurs restent en session et s'affichent sur la page ouverte.
 */
class EnvoiSansRechargement
{
    public const ENTETE = 'X-Envoi-Sans-Rechargement';

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->headers->has(self::ENTETE) && $response instanceof RedirectResponse) {
            return new JsonResponse(['redirection' => $response->getTargetUrl()]);
        }

        return $response;
    }
}
