<?php

declare(strict_types=1);

use App\Feature\MarketPairs\Controller\MarketPairController;
use Illuminate\Support\Facades\Route;

Route::prefix('workbench')->name('workbench.')->group(function (): void {
    Route::resource('market-pairs', MarketPairController::class)
        ->parameters(['market-pairs' => 'marketPair']);
});
