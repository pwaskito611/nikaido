<?php

declare(strict_types=1);

namespace App\Infrastructure\Providers;

use App\DataImport\Contracts\ExternalMarketDataProvider;
use App\DataImport\Providers\AlphaVantageDataProvider;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ExternalMarketDataProvider::class, AlphaVantageDataProvider::class);
    }
}
