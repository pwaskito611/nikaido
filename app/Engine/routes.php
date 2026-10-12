<?php

declare(strict_types=1);

use App\DataImport\Controllers\DataImportApiController;
use App\Engine\Controllers\EngineController;
use Illuminate\Support\Facades\Route;

Route::post('/engine/specific-pair-test', [EngineController::class, 'runSpecificPairTest']);
Route::post('/data-import/candles', [DataImportApiController::class, 'receive'])
    ->middleware('throttle:120,1');
