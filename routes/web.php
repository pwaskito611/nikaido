<?php

use Illuminate\Support\Facades\Route;

require app_path('Features/Workbench/routes.php');
require app_path('Features/DataImport/routes.php');

Route::redirect('/', '/workbench');
