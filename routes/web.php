<?php

require app_path('Feature/Dashboard/routes.php');
require app_path('Feature/MarketTypes/routes.php');
require app_path('Feature/MarketPairs/routes.php');
require app_path('Feature/Timeframes/routes.php');
require app_path('Feature/MarketPrice/routes.php');
require app_path('Feature/MarketExploration/routes.php');
require app_path('Feature/Backtest/routes.php');
require app_path('Feature/DataImport/routes.php');

Route::redirect('/', '/workbench');
