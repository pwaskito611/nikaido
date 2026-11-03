<?php

declare(strict_types=1);

namespace App\Feature\MarketExploration\Action;

use App\Feature\MarketPrice\Repository\MarketPriceRepository;
use Illuminate\Support\Carbon;

class LoadMarketExplorationCandlesAction
{
    public function __construct(
        private readonly MarketPriceRepository $marketPrices,
    ) {}

    /**
     * @return list<array{time: string, open: float, high: float, low: float, close: float}>
     */
    public function execute(int $pairId, int $timeframeTypeId, string $from, string $to): array
    {
        return $this->marketPrices
            ->candlesBetween(
                $pairId,
                $timeframeTypeId,
                Carbon::parse($from)->startOfDay()->toDateTimeString(),
                Carbon::parse($to)->endOfDay()->toDateTimeString(),
            )
            ->map(static fn ($price): array => [
                'time' => $price->price_date->format('Y-m-d H:i'),
                'open' => (float) $price->open,
                'high' => (float) $price->high,
                'low' => (float) $price->low,
                'close' => (float) $price->close,
            ])
            ->toArray();
    }
}
