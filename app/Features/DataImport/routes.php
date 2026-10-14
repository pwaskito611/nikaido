<?php

declare(strict_types=1);

use App\Features\DataImport\Controllers\DataImportController;
use Illuminate\Support\Facades\Route;

Route::prefix('workbench')->name('workbench.')->group(function (): void {
    Route::get('/data-import', [DataImportController::class, 'index'])->name('data-import.index');
    Route::post('/data-import/incoming-sessions', [DataImportController::class, 'startIncoming'])
        ->name('data-import.incoming-sessions.start');
    Route::post('/data-import/incoming-sessions/{sessionId}/stop', [DataImportController::class, 'stopIncoming'])
        ->whereNumber('sessionId')
        ->name('data-import.incoming-sessions.stop');
    Route::post('/data-import/external-provider', [DataImportController::class, 'importExternalProvider'])
        ->name('data-import.external-provider');
});
