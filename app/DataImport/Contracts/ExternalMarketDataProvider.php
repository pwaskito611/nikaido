<?php

declare(strict_types=1);

namespace App\DataImport\Contracts;

interface ExternalMarketDataProvider
{
    /**
     * @return list<array{time: string, open: float, high: float, low: float, close: float}>
     */
    public function fetch(
        string $credential,
        string $baseSymbol,
        string $quoteSymbol,
        string $timeframe,
    ): array;
}
