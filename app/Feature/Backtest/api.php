<?php

declare(strict_types=1);

use App\Feature\Backtest\Controller\BacktestController;
use Illuminate\Support\Facades\Route;

Route::post('/engine/specific-pair-test', [BacktestController::class, 'runSpecificPairTest']);
