<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Codespaces / reverse proxies terminate HTTPS: trust them so asset,
        // Livewire and Filament URLs are generated as https.
        $middleware->trustProxies(at: '*');

        // Game screens stay open on a TV for hours; an expired CSRF token would stop the game
        // with "This page has expired". Livewire signs its own component state, and the session
        // cookie is SameSite=lax (not sent on cross-site requests), so this is safe here.
        $middleware->validateCsrfTokens(except: ['livewire/update', 'livewire*/update', 'alefya/api/*', 'akher/api/*']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
