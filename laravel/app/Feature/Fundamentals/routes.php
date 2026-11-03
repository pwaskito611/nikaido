<?php

declare(strict_types=1);

use App\Feature\Fundamentals\Controller\FundamentalContentController;
use App\Feature\Fundamentals\Controller\FundamentalController;
use Illuminate\Support\Facades\Route;

Route::prefix('workbench')->name('workbench.')->group(function (): void {
    Route::resource('fundamentals', FundamentalController::class)
        ->parameters(['fundamentals' => 'fundamental']);

    Route::get('fundamentals/{fundamental}/contents/create', [FundamentalContentController::class, 'create'])
        ->name('fundamentals.contents.create');
    Route::post('fundamentals/{fundamental}/contents', [FundamentalContentController::class, 'store'])
        ->name('fundamentals.contents.store');
    Route::get('fundamentals/{fundamental}/contents/{content}/edit', [FundamentalContentController::class, 'edit'])
        ->name('fundamentals.contents.edit');
    Route::put('fundamentals/{fundamental}/contents/{content}', [FundamentalContentController::class, 'update'])
        ->name('fundamentals.contents.update');
    Route::delete('fundamentals/{fundamental}/contents/{content}', [FundamentalContentController::class, 'destroy'])
        ->name('fundamentals.contents.destroy');
});
