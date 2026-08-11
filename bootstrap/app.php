<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\ServiceTokenAuthentication;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Support\Env;

/*
 * Premenné z .env len do $_ENV/$_SERVER, nie do prostredia procesu cez putenv().
 *
 * Apache na Windows (mpm_winnt) beží ako jeden proces s vláknami, takže putenv()
 * vidia aj súbežné požiadavky ostatných aplikácií na tom istom serveri. Projekty
 * volajú Account vnorenou HTTP požiadavkou a bez tohto riadku si navzájom
 * podstrkujú DB_DATABASE — Account potom hľadá `service_clients` v databáze
 * volajúceho. Superglobály sú na rozdiel od prostredia procesu viazané
 * na požiadavku.
 */
Env::disablePutenv();

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            SetLocale::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        // Jazyk API odpovede sa riadi koncovým zákazníkom, nie Accountom.
        $middleware->api(prepend: [
            SetLocale::class,
        ]);

        $middleware->alias([
            'service' => ServiceTokenAuthentication::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
