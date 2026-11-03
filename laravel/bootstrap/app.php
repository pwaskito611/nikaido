<?php

use App\Platform\Console\Commands\NikaidoStartCommand;
use App\Platform\Console\Commands\NikaidoStopCommand;
use App\Platform\Infrastructure\NikaidoLaravelApplication;
use Illuminate\Foundation\Configuration\Middleware;

$projectRoot = dirname(__DIR__, 2);

$app = NikaidoLaravelApplication::configure(basePath: $projectRoot)
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: [
            $projectRoot.'/laravel/app/Feature/Backtest/api.php',
            $projectRoot.'/laravel/app/Feature/DataImport/api.php',
            $projectRoot.'/laravel/app/Feature/Fundamentals/api.php',
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
    ->withExceptions()
    ->create();

return $app;
