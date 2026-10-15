<?php

declare(strict_types=1);

use App\Feature\MarketTypes\Controller\MarketTypeController;
use Illuminate\Support\Facades\Route;

Route::prefix('workbench')->name('workbench.')->group(function (): void {
    Route::resource('market-types', MarketTypeController::class)
        ->parameters(['market-types' => 'marketType']);
});
