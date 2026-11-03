<?php

declare(strict_types=1);

use App\Feature\Fundamentals\Controller\FundamentalImportApiController;
use Illuminate\Support\Facades\Route;

Route::post('/data-import/fundamentals/observations', [FundamentalImportApiController::class, 'receive'])
    ->middleware('throttle:120,1');
