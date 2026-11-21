<?php

use App\Http\Middleware\PolitiqueSecuriteContenu;
use App\Http\Middleware\ProtegerFormulaires;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Derrière Caddy (HTTPS) : seul proxy à joindre l'application, il fixe lui-même les en-têtes X-Forwarded.
        // Sans cela, les liens signés (accusés, procès-verbaux) seraient en http et les limites compteraient l'IP de Caddy.
        $middleware->trustProxies(at: '*');
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('espace'));
        $middleware->web(append: [PolitiqueSecuriteContenu::class], replace: [PreventRequestForgery::class => ProtegerFormulaires::class]);
        // Les navigateurs envoient les rapports de violation sans jeton CSRF.
        $middleware->preventRequestForgery(except: ['csp-rapport']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
