<?php

declare(strict_types=1);

use App\Feature\MarketExploration\Controller\MarketExplorationController;
use Illuminate\Support\Facades\Route;

Route::get('/workbench/market-exploration', [MarketExplorationController::class, 'index'])
    ->name('workbench.market-exploration.index');
