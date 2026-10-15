<?php

use App\Platform\Console\Commands\NikaidoStartCommand;
use App\Platform\Console\Commands\NikaidoStopCommand;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: [
            __DIR__.'/../app/Feature/Backtest/api.php',
            __DIR__.'/../app/Feature/DataImport/api.php',
        ],
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        NikaidoStartCommand::class,
        NikaidoStopCommand::class,
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash('external_provider_credential');
    })->create();
