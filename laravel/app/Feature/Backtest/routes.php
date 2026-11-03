<?php

declare(strict_types=1);

use App\Feature\Backtest\Controller\BacktestController;
use Illuminate\Support\Facades\Route;

Route::prefix('workbench')->name('workbench.')->group(function (): void {
    Route::get('/tests/create', [BacktestController::class, 'createTest'])->name('tests.create');
    Route::post('/tests', [BacktestController::class, 'runTest'])->name('tests.run');
    Route::get('/tests/market-type-runs/{marketTypeBacktestRun}', [BacktestController::class, 'marketTypeRun'])
        ->name('tests.market-type-runs.show');
});
