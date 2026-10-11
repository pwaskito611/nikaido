<?php

declare(strict_types=1);

use App\Engine\Controllers\EngineController;
use Illuminate\Support\Facades\Route;

Route::post('/engine/specific-pair-test', [EngineController::class, 'runSpecificPairTest']);
