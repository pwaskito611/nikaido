<?php

declare(strict_types=1);

use App\Feature\Dashboard\Controller\DashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/workbench', [DashboardController::class, 'index'])->name('workbench.index');
