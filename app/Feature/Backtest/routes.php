<?php

declare(strict_types=1);

use App\Feature\Backtest\Controller\BacktestController;
use Illuminate\Support\Facades\Route;

Route::prefix('workbench')->name('workbench.')->group(function (): void {
    Route::get('/tests/create', [BacktestController::class, 'createTest'])->name('tests.create');
    Route::post('/tests', [BacktestController::class, 'runTest'])->name('tests.run');
    Route::get('/tests/results', [BacktestController::class, 'results'])->name('tests.results');
    Route::get('/tests/market-type-runs/{marketTypeBacktestRun}', [BacktestController::class, 'marketTypeRun'])
        ->name('tests.market-type-runs.show');
    Route::get('/tests/market-type-runs/{marketTypeBacktestRun}/pairs/{pairRun}', [BacktestController::class, 'marketTypePairResult'])
        ->name('tests.market-type-runs.pairs.show');
    Route::get('/tests/market-type-runs/{marketTypeBacktestRun}/overall', [BacktestController::class, 'marketTypeOverallResult'])
        ->name('tests.market-type-runs.overall');
});
