<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;

/**
 * Protection des formulaires contre la falsification de requête (jeton CSRF), sans le cookie XSRF-TOKEN : ce cookie
 * ne sert qu'aux clients JavaScript qui le relisent (axios) et signale Laravel. Les formulaires et Livewire envoient
 * le jeton placé dans la page.
 */
class ProtegerFormulaires extends PreventRequestForgery
{
    /**
     * @var bool
     */
    protected $addHttpCookie = false;
}
