<?php

declare(strict_types=1);

use App\Feature\Timeframes\Controller\TimeframeTypeController;
use Illuminate\Support\Facades\Route;

Route::prefix('workbench')->name('workbench.')->group(function (): void {
    Route::resource('timeframes', TimeframeTypeController::class)
        ->parameters(['timeframes' => 'timeframe']);
});
