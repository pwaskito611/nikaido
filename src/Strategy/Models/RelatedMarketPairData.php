<?php

declare(strict_types=1);

namespace Nikaido\Strategy\Models;

use DateTimeInterface;
use InvalidArgumentException;

final readonly class RelatedMarketPairData
{
    /** @var array<string, object> */
    public array $candlesByTimestamp;

    /**
     * @param  iterable<object>  $candles
     */
    public function __construct(
        public int $marketPairId,
        public string $base,
        public string $quote,
        public ?int $timeframeTypeId,
        iterable $candles,
    ) {
        $candlesByTimestamp = [];
        foreach ($candles as $candle) {
            if (! isset($candle->price_date) || ! $candle->price_date instanceof DateTimeInterface) {
                throw new InvalidArgumentException('Related market pair candles must have a timestamp.');
            }
            $candlesByTimestamp[$candle->price_date->format('Y-m-d H:i:s')] = $candle;
        }

        $this->candlesByTimestamp = $candlesByTimestamp;
    }

    public function candleAt(DateTimeInterface|string $timestamp): ?object
    {
        $key = $timestamp instanceof DateTimeInterface
            ? $timestamp->format('Y-m-d H:i:s')
            : $timestamp;

        return $this->candlesByTimestamp[$key] ?? null;
    }
}
