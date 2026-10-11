<?php

declare(strict_types=1);

use App\Workbench\Controllers\MarketPairController;
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
    Route::resource('timeframes', TimeframeTypeController::class)
        ->parameters(['timeframes' => 'timeframe']);

    Route::get('/tests/create', [WorkbenchController::class, 'createTest'])->name('tests.create');
    Route::post('/tests', [WorkbenchController::class, 'runTest'])->name('tests.run');
    Route::get('/tests/results', [WorkbenchController::class, 'results'])->name('tests.results');
});
