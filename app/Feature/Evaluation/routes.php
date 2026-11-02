<?php

declare(strict_types=1);

use App\Feature\Evaluation\Controller\EvaluationController;
use Illuminate\Support\Facades\Route;

Route::prefix('workbench')->name('workbench.')->group(function (): void {
    Route::get('/tests/results', [EvaluationController::class, 'results'])->name('tests.results');
    Route::get('/tests/market-type-runs/{marketTypeBacktestRun}/pairs/{pairRun}', [EvaluationController::class, 'marketTypePairResult'])
        ->name('tests.market-type-runs.pairs.show');
    Route::get('/tests/market-type-runs/{marketTypeBacktestRun}/overall', [EvaluationController::class, 'marketTypeOverallResult'])
        ->name('tests.market-type-runs.overall');
});
