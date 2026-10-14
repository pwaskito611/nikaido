<?php

declare(strict_types=1);

use App\Features\DataImport\Controllers\DataImportApiController;
use Illuminate\Support\Facades\Route;

Route::post('/data-import/candles', [DataImportApiController::class, 'receive'])
    ->middleware('throttle:120,1');
