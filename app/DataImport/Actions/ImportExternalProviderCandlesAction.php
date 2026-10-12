<?php

declare(strict_types=1);

namespace App\DataImport\Actions;

use App\DataImport\Contracts\ExternalMarketDataProvider;
use App\Engine\Repositories\MarketPairRepository;
use App\Engine\Repositories\MarketPriceRepository;
use App\Engine\Repositories\TimeframeTypeRepository;

class ImportExternalProviderCandlesAction
{
    public function __construct(
        private readonly ExternalMarketDataProvider $provider,
        private readonly MarketPairRepository $marketPairs,
        private readonly TimeframeTypeRepository $timeframes,
        private readonly MarketPriceRepository $marketPrices,
    ) {}

    public function execute(
        string $credential,
        int $pairId,
        int $timeframeTypeId,
    ): int {
        $pair = $this->marketPairs->findOrFail($pairId);
        $timeframe = $this->timeframes->findOrFail($timeframeTypeId);
        $candles = $this->provider->fetch($credential, $pair->base, $pair->quote, $timeframe->name);
        $records = array_map(static function (array $candle) use ($pair, $timeframe): array {
            $open = $candle['open'];
            $high = $candle['high'];
            $low = $candle['low'];
            $close = $candle['close'];

            return [
                'pair_id' => $pair->id,
                'timeframe_type_id' => $timeframe->id,
                'price_date' => $candle['time'],
                'open' => $open,
                'high' => $high,
                'low' => $low,
                'close' => $close,
                'bid_open' => null,
                'bid_high' => null,
                'bid_low' => null,
                'bid_close' => null,
                'ask_open' => null,
                'ask_high' => null,
                'ask_low' => null,
                'ask_close' => null,
            ];
        }, $candles);

        return $this->marketPrices->importCandles($records);
    }
}
