<?php

namespace App\Engine\Actions;

use App\Engine\Models\MarketPrice;
use App\Engine\Repositories\MarketPairRepository;
use App\Strategy\Config\SpecificPairTestConfig;
use App\Strategy\Strategy;
use Exact\Collection\Vec;
use Illuminate\Support\Collection;

class RunSpecificPairBacktestAction
{
    public function __construct(
        private readonly MarketPairRepository $marketPairRepository,
    ) {}

    /**
     * @return Vec<array{candle: MarketPrice, result: bool}>
     */
    public function execute(SpecificPairTestConfig $config, Strategy $strategy): Vec
    {
        $pair = $this->marketPairRepository->findWithPricesBetween(
            $config->base,
            $config->quote,
            $config->from,
            $config->to,
        );

        $history = new Collection;
        $results = Vec::empty();

        foreach ($pair->prices as $candle) {
            if ($this->isActive($candle, $config)) {
                $results = $results->append([
                    'candle' => $candle,
                    'result' => $strategy->execute($candle, $history),
                ]);
            }

            $history->push($candle);
        }

        return $results;
    }

    private function isActive(MarketPrice $candle, SpecificPairTestConfig $config): bool
    {
        $timestamp = $candle->price_date;

        if (in_array((int) $timestamp->format('N'), $config->excludedDays, true)) {
            return false;
        }

        if ($config->activeFrom === false) {
            return true;
        }

        $time = $timestamp->format('H:i');

        if ($config->activeFrom <= $config->activeUntil) {
            return $time >= $config->activeFrom && $time <= $config->activeUntil;
        }

        return $time >= $config->activeFrom || $time <= $config->activeUntil;
    }
}
