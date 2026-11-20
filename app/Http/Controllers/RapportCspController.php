<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Reçoit les violations de la politique de sécurité du contenu signalées par les navigateurs et les écrit dans les
 * journaux (docker compose logs app), pour vérifier les écrans avant de passer la politique en mode bloquant.
 */
class RapportCspController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $rapport = json_decode($request->getContent(), true)['csp-report'] ?? null;

        if (is_array($rapport)) {
            Log::warning('Violation de la politique de sécurité du contenu', array_map(
                fn ($valeur) => is_string($valeur) ? mb_substr($valeur, 0, 300) : $valeur,
                array_intersect_key($rapport, array_flip(['document-uri', 'violated-directive', 'effective-directive', 'blocked-uri', 'source-file', 'line-number'])),
            ));
        }

        return response()->noContent();
    }
}
