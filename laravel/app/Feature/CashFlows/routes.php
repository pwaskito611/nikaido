<?php

declare(strict_types=1);

use App\Feature\CashFlows\Controller\CashFlowImportSessionController;
use App\Feature\CashFlows\Controller\MarketPairCashFlowController;
use Illuminate\Support\Facades\Route;

Route::prefix('workbench')->name('workbench.')->group(function (): void {
    Route::post('data-import/cash-flow-sessions', [CashFlowImportSessionController::class, 'start'])
        ->name('data-import.cash-flow-sessions.start');
    Route::post(
        'data-import/cash-flow-sessions/{marketPair}/{sessionId}/stop',
        [CashFlowImportSessionController::class, 'stop'],
    )->whereNumber(['marketPair', 'sessionId'])->name('data-import.cash-flow-sessions.stop');

    Route::resource('cash-flows', MarketPairCashFlowController::class)
        ->parameters(['cash-flows' => 'cashFlow']);
});
