<?php

use App\Console\Commands\NikaidoStartCommand;
use App\Console\Commands\NikaidoStopCommand;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: [
            __DIR__.'/../app/Engine/routes.php',
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
