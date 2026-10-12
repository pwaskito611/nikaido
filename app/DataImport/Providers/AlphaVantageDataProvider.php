<?php

declare(strict_types=1);

namespace App\DataImport\Providers;

use App\DataImport\Contracts\ExternalMarketDataProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class AlphaVantageDataProvider implements ExternalMarketDataProvider
{
    /**
     * @return list<array{time: string, open: float, high: float, low: float, close: float}>
     */
    public function fetch(
        string $credential,
        string $baseSymbol,
        string $quoteSymbol,
        string $timeframe,
    ): array {
        [$function, $interval, $aggregateHours] = $this->providerParameters($timeframe);
        $parameters = [
            'function' => $function,
            'from_symbol' => strtoupper($baseSymbol),
            'to_symbol' => strtoupper($quoteSymbol),
            'apikey' => $credential,
            'outputsize' => 'full',
        ];
        if ($interval !== null) {
            $parameters['interval'] = $interval;
        }

        try {
            $response = Http::acceptJson()
                ->connectTimeout(5)
                ->timeout(30)
                ->get('https://www.alphavantage.co/query', $parameters);
        } catch (ConnectionException) {
            throw ValidationException::withMessages([
                'external_provider_credential' => 'Could not connect to the external data provider. Check the network and try again.',
            ]);
        }

        if (! $response->successful()) {
            throw ValidationException::withMessages([
                'external_provider_credential' => 'The external data provider returned HTTP '.$response->status().'.',
            ]);
        }

        $payload = $response->json();
        if (! is_array($payload)) {
            throw new RuntimeException('The external data provider returned an invalid response.');
        }

        foreach (['Error Message', 'Information', 'Note'] as $messageKey) {
            if (isset($payload[$messageKey])) {
                throw ValidationException::withMessages([
                    'external_provider_credential' => (string) $payload[$messageKey],
                ]);
            }
        }

        $seriesKey = collect(array_keys($payload))
            ->first(static fn (string $key): bool => str_starts_with($key, 'Time Series FX ('));
        if (! is_string($seriesKey) || ! is_array($payload[$seriesKey])) {
            throw new RuntimeException('The external data provider response did not contain an FX time series.');
        }

        $candles = [];
        foreach ($payload[$seriesKey] as $time => $values) {
            if (! is_string($time) || ! is_array($values)
                || ! isset($values['1. open'], $values['2. high'], $values['3. low'], $values['4. close'])
                || ! is_numeric($values['1. open'])
                || ! is_numeric($values['2. high'])
                || ! is_numeric($values['3. low'])
                || ! is_numeric($values['4. close'])) {
                throw new RuntimeException('The external data provider returned a malformed FX candle.');
            }

            $dateTime = $time;
            if ($function === 'FX_INTRADAY') {
                if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/D', $time) === 1) {
                    $dateTime .= ':00';
                } elseif (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/D', $time) !== 1) {
                    throw new RuntimeException('The external data provider returned a malformed FX candle timestamp.');
                }
            } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/D', $time) === 1) {
                $dateTime .= ' 00:00:00';
            } else {
                throw new RuntimeException('The external data provider returned a malformed FX candle timestamp.');
            }

            $candles[] = [
                'time' => $dateTime,
                'open' => (float) $values['1. open'],
                'high' => (float) $values['2. high'],
                'low' => (float) $values['3. low'],
                'close' => (float) $values['4. close'],
            ];
        }

        if ($candles === []) {
            throw ValidationException::withMessages([
                'timeframe_type_id' => 'The external data provider returned no candles for this pair and timeframe.',
            ]);
        }

        usort($candles, static fn (array $left, array $right): int => $left['time'] <=> $right['time']);

        return $aggregateHours > 1 ? $this->aggregateHourlyCandles($candles, $aggregateHours) : $candles;
    }

    /**
     * @return array{string, ?string, int}
     */
    private function providerParameters(string $timeframe): array
    {
        $name = strtolower(trim($timeframe));
        if ($name === '1d' || $name === 'daily' || $name === 'day') {
            return ['FX_DAILY', null, 1];
        }
        if ($name === '1w' || $name === 'weekly' || $name === 'week') {
            return ['FX_WEEKLY', null, 1];
        }
        if ($name === '1mo' || $name === 'monthly' || $name === 'month') {
            return ['FX_MONTHLY', null, 1];
        }

        if (preg_match('/^(\d+)\s*(m|min|h|hr|hour|hours)$/', $name, $matches) !== 1) {
            throw ValidationException::withMessages([
                'timeframe_type_id' => "The external data provider does not support the '{$timeframe}' timeframe.",
            ]);
        }

        $amount = (int) $matches[1];
        $unit = $matches[2];
        if ($unit === 'm' || $unit === 'min') {
            if ($amount === 60) {
                return ['FX_INTRADAY', '60min', 1];
            }

            $interval = match ($amount) {
                1 => '1min',
                5 => '5min',
                15 => '15min',
                30 => '30min',
                default => null,
            };
            if ($interval === null) {
                throw ValidationException::withMessages([
                    'timeframe_type_id' => 'This provider connector supports minute imports at 1, 5, 15, 30, or 60 minutes.',
                ]);
            }

            return ['FX_INTRADAY', $interval, 1];
        }

        if (in_array($unit, ['h', 'hr', 'hour', 'hours'], true)) {
            if ($amount < 1 || $amount > 24 || 24 % $amount !== 0) {
                throw ValidationException::withMessages([
                    'timeframe_type_id' => 'This provider connector supports whole-hour timeframes that divide 24 hours.',
                ]);
            }

            return ['FX_INTRADAY', '60min', $amount];
        }
    }

    /**
     * @param  list<array{time: string, open: float, high: float, low: float, close: float}>  $candles
     * @return list<array{time: string, open: float, high: float, low: float, close: float}>
     */
    private function aggregateHourlyCandles(array $candles, int $hours): array
    {
        $aggregated = [];
        foreach ($candles as $candle) {
            $date = new \DateTimeImmutable($candle['time']);
            $bucketHour = intdiv((int) $date->format('G'), $hours) * $hours;
            $bucket = $date->setTime($bucketHour, 0)->format('Y-m-d H:i:s');

            if (! isset($aggregated[$bucket])) {
                $aggregated[$bucket] = [...$candle, 'time' => $bucket];

                continue;
            }

            $aggregated[$bucket]['high'] = max($aggregated[$bucket]['high'], $candle['high']);
            $aggregated[$bucket]['low'] = min($aggregated[$bucket]['low'], $candle['low']);
            $aggregated[$bucket]['close'] = $candle['close'];
        }

        return array_values($aggregated);
    }
}
