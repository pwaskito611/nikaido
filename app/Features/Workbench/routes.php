<?php

declare(strict_types=1);

use App\Features\Workbench\Controllers\MarketExplorationController;
use App\Features\Workbench\Controllers\MarketPairController;
use App\Features\Workbench\Controllers\MarketPriceController;
use App\Features\Workbench\Controllers\MarketTypeController;
use App\Features\Workbench\Controllers\TimeframeTypeController;
use App\Features\Workbench\Controllers\WorkbenchController;
use Illuminate\Support\Facades\Route;

Route::prefix('workbench')->name('workbench.')->group(function (): void {
    Route::get('/', [WorkbenchController::class, 'index'])->name('index');

    Route::resource('market-types', MarketTypeController::class)
        ->parameters(['market-types' => 'marketType']);
    Route::resource('market-pairs', MarketPairController::class)
        ->parameters(['market-pairs' => 'marketPair']);
    Route::resource('market-prices', MarketPriceController::class)
        ->parameters(['market-prices' => 'marketPrice']);
    Route::get('/market-exploration', [MarketExplorationController::class, 'index'])
        ->name('market-exploration.index');
    Route::resource('timeframes', TimeframeTypeController::class)
        ->parameters(['timeframes' => 'timeframe']);

    Route::get('/tests/create', [WorkbenchController::class, 'createTest'])->name('tests.create');
    Route::post('/tests', [WorkbenchController::class, 'runTest'])->name('tests.run');
    Route::get('/tests/results', [WorkbenchController::class, 'results'])->name('tests.results');
    Route::get('/tests/market-type-runs/{marketTypeBacktestRun}', [WorkbenchController::class, 'marketTypeRun'])
        ->name('tests.market-type-runs.show');
    Route::get('/tests/market-type-runs/{marketTypeBacktestRun}/pairs/{pairRun}', [WorkbenchController::class, 'marketTypePairResult'])
        ->name('tests.market-type-runs.pairs.show');
    Route::get('/tests/market-type-runs/{marketTypeBacktestRun}/overall', [WorkbenchController::class, 'marketTypeOverallResult'])
        ->name('tests.market-type-runs.overall');
});
