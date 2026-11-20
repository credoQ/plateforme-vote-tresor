<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Politique de sécurité du contenu (Content-Security-Policy) des pages : seules les ressources du site sont chargées,
 * le site ne s'affiche pas dans le cadre d'un autre site et les formulaires ne partent que vers lui.
 *
 * Livewire et Alpine évaluent du code à la volée (« unsafe-eval ») et les gabarits ont des scripts en ligne (thème,
 * configuration de Livewire). Mode « rapport » : le navigateur signale les violations sans rien bloquer ; mode
 * « bloquant » : il les bloque. Les violations sont envoyées à la route csp.rapport.
 */
class PolitiqueSecuriteContenu
{
    /**
     * @var array<string, string>
     */
    private const DIRECTIVES = [
        'default-src' => "'self'",
        'script-src' => "'self' 'unsafe-inline' 'unsafe-eval'",
        'style-src' => "'self' 'unsafe-inline'",
        'img-src' => "'self' data: blob:",
        'font-src' => "'self' data:",
        'connect-src' => "'self'",
        'frame-src' => "'self'",
        'frame-ancestors' => "'self'",
        'form-action' => "'self'",
        'base-uri' => "'self'",
        'object-src' => "'none'",
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $mode = config('app.csp');
        if (! in_array($mode, ['rapport', 'bloquant'], true) || $response->headers->has('Content-Security-Policy')) {
            return $response;
        }

        $politique = collect(self::DIRECTIVES)->map(fn (string $valeur, string $directive): string => "{$directive} {$valeur}")
            ->push('report-uri '.route('csp.rapport', absolute: false))
            ->implode('; ');

        $response->headers->set($mode === 'bloquant' ? 'Content-Security-Policy' : 'Content-Security-Policy-Report-Only', $politique);

        return $response;
    }
}
