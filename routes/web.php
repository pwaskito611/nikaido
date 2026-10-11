<?php

use Illuminate\Support\Facades\Route;

require app_path('Workbench/routes.php');

Route::redirect('/', '/workbench');
