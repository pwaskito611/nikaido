<?php

declare(strict_types=1);

use App\Feature\News\Controller\NewsController;
use App\Feature\News\Controller\NewsImportSessionController;
use Illuminate\Support\Facades\Route;

Route::prefix('workbench')->name('workbench.')->group(function (): void {
    Route::resource('news', NewsController::class)->except(['show']);
    Route::post('/data-import/news-sessions', [NewsImportSessionController::class, 'start'])
        ->name('data-import.news-sessions.start');
    Route::post('/data-import/news-sessions/{sessionId}/stop', [NewsImportSessionController::class, 'stop'])
        ->whereNumber('sessionId')
        ->name('data-import.news-sessions.stop');
});
