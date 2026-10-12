<?php

declare(strict_types=1);

use App\DataImport\Controllers\DataImportController;
use App\Workbench\Controllers\MarketExplorationController;
use App\Workbench\Controllers\MarketPairController;
use App\Workbench\Controllers\MarketPriceController;
use App\Workbench\Controllers\MarketTypeController;
use App\Workbench\Controllers\TimeframeTypeController;
use App\Workbench\Controllers\WorkbenchController;
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
    Route::get('/data-import', [DataImportController::class, 'index'])->name('data-import.index');
    Route::post('/data-import/incoming-sessions', [DataImportController::class, 'startIncoming'])
        ->name('data-import.incoming-sessions.start');
    Route::post('/data-import/incoming-sessions/{sessionId}/stop', [DataImportController::class, 'stopIncoming'])
        ->whereNumber('sessionId')
        ->name('data-import.incoming-sessions.stop');
    Route::post('/data-import/external-provider', [DataImportController::class, 'importExternalProvider'])
        ->name('data-import.external-provider');
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
