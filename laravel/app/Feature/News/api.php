<?php

declare(strict_types=1);

use App\Feature\News\Controller\NewsImportApiController;
use Illuminate\Support\Facades\Route;

Route::post('/data-import/news/articles', NewsImportApiController::class)
    ->middleware('throttle:60,1');
