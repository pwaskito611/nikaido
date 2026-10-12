<?php

declare(strict_types=1);

namespace App\DataImport\Actions;

use App\DataImport\Models\DataImportSession;
use App\Engine\Repositories\MarketPriceRepository;
use Illuminate\Validation\ValidationException;

class ReceivePushedCandlesAction
{
    public function __construct(
        private readonly MarketPriceRepository $marketPrices,
    ) {}

    /**
     * @param  list<array{
     *     time: string,
     *     open: int|float|string,
     *     high: int|float|string,
     *     low: int|float|string,
     *     close: int|float|string,
     *     bid_open?: int|float|string|null,
     *     bid_high?: int|float|string|null,
     *     bid_low?: int|float|string|null,
     *     bid_close?: int|float|string|null,
     *     ask_open?: int|float|string|null,
     *     ask_high?: int|float|string|null,
     *     ask_low?: int|float|string|null,
     *     ask_close?: int|float|string|null
     * }>  $candles
     */
    public function execute(DataImportSession $session, array $candles): int
    {
        $this->assertConsistentCandles($candles);

        return $this->marketPrices->importCandles($this->recordsForSession($session, $candles));
    }

    /**
     * @param  list<array<string, mixed>>  $candles
     */
    private function assertConsistentCandles(array $candles): void
    {
        foreach ($candles as $index => $candle) {
            if ($candle['high'] < max($candle['open'], $candle['close'], $candle['low'])
                || $candle['low'] > min($candle['open'], $candle['close'], $candle['high'])) {
                throw ValidationException::withMessages([
                    "candles.{$index}.high" => "Candle {$index} has inconsistent high/low values.",
                ]);
            }

            foreach (['bid', 'ask'] as $side) {
                $open = $candle[$side.'_open'] ?? null;
                $high = $candle[$side.'_high'] ?? null;
                $low = $candle[$side.'_low'] ?? null;
                $close = $candle[$side.'_close'] ?? null;
                if ($open !== null && $high !== null && $low !== null && $close !== null
                    && ($high < max($open, $close, $low) || $low > min($open, $close, $high))) {
                    throw ValidationException::withMessages([
                        "candles.{$index}.{$side}_high" => "Candle {$index} has inconsistent {$side} high/low values.",
                    ]);
                }
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $candles
     * @return list<array<string, mixed>>
     */
    private function recordsForSession(DataImportSession $session, array $candles): array
    {
        return array_map(static function (array $candle) use ($session): array {
            $open = (float) $candle['open'];
            $high = (float) $candle['high'];
            $low = (float) $candle['low'];
            $close = (float) $candle['close'];

            return [
                'pair_id' => $session->pair_id,
                'timeframe_type_id' => $session->timeframe_type_id,
                'price_date' => $candle['time'],
                'open' => $open,
                'high' => $high,
                'low' => $low,
                'close' => $close,
                'bid_open' => isset($candle['bid_open']) ? (float) $candle['bid_open'] : null,
                'bid_high' => isset($candle['bid_high']) ? (float) $candle['bid_high'] : null,
                'bid_low' => isset($candle['bid_low']) ? (float) $candle['bid_low'] : null,
                'bid_close' => isset($candle['bid_close']) ? (float) $candle['bid_close'] : null,
                'ask_open' => isset($candle['ask_open']) ? (float) $candle['ask_open'] : null,
                'ask_high' => isset($candle['ask_high']) ? (float) $candle['ask_high'] : null,
                'ask_low' => isset($candle['ask_low']) ? (float) $candle['ask_low'] : null,
                'ask_close' => isset($candle['ask_close']) ? (float) $candle['ask_close'] : null,
            ];
        }, $candles);
    }
}
