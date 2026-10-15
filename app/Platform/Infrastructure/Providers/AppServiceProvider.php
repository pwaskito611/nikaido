<?php

declare(strict_types=1);

namespace App\Platform\Infrastructure\Providers;

use App\Feature\DataImport\Contract\ExternalMarketDataProvider;
use App\Feature\DataImport\Provider\AlphaVantageDataProvider;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ExternalMarketDataProvider::class, AlphaVantageDataProvider::class);
    }
}
