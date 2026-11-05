<?php

declare(strict_types=1);

use App\Feature\CashFlows\Controller\CashFlowImportApiController;
use Illuminate\Support\Facades\Route;

Route::post('/data-import/cash-flows/events', [CashFlowImportApiController::class, 'receive'])
    ->middleware('throttle:120,1');
