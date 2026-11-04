<?php

declare(strict_types=1);

use App\Feature\MarketExploration\Controller\MarketExplorationController;
use Illuminate\Support\Facades\Route;

Route::post('/market-exploration/run-strategy', [MarketExplorationController::class, 'runStrategy'])
    ->name('market-exploration.run-strategy');
