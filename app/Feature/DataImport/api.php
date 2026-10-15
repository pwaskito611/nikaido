<?php

declare(strict_types=1);

use App\Feature\DataImport\Controller\DataImportApiController;
use Illuminate\Support\Facades\Route;

Route::post('/data-import/candles', [DataImportApiController::class, 'receive'])
    ->middleware('throttle:120,1');
