<?php

declare(strict_types=1);

use App\Feature\MarketPrice\Controller\MarketPriceController;
use Illuminate\Support\Facades\Route;

Route::prefix('workbench')->name('workbench.')->group(function (): void {
    Route::resource('market-prices', MarketPriceController::class)
        ->parameters(['market-prices' => 'marketPrice']);
});
