<?php

declare(strict_types=1);

namespace App\Engine\Repositories;

use App\Engine\Models\MarketPrice;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class MarketPriceRepository
{
    public function __construct(
        private readonly MarketPrice $model,
    ) {}

    public function find(int $id): ?MarketPrice
    {
        return $this->model->newQuery()->find($id);
    }

    public function findOrFail(int $id): MarketPrice
    {
        return $this->model->newQuery()
            ->with(['pair', 'timeframeType'])
            ->findOrFail($id);
    }

    public function paginate(
        ?int $pairId = null,
        ?int $timeframeTypeId = null,
        int $perPage = 25,
    ): LengthAwarePaginator {
        return $this->model->newQuery()
            ->with(['pair', 'timeframeType'])
            ->when($pairId !== null, fn ($query) => $query->where('pair_id', $pairId))
            ->when($timeframeTypeId !== null, fn ($query) => $query->where('timeframe_type_id', $timeframeTypeId))
            ->orderByDesc('price_date')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @return Collection<int, MarketPrice>
     */
    public function candlesBetween(int $pairId, int $timeframeTypeId, string $from, string $to): Collection
    {
        return $this->model->newQuery()
            ->where('pair_id', $pairId)
            ->where('timeframe_type_id', $timeframeTypeId)
            ->whereBetween('price_date', [$from, $to])
            ->orderBy('price_date')
            ->orderBy('id')
            ->get([
                'price_date',
                'open',
                'high',
                'low',
                'close',
                'bid_open',
                'bid_high',
                'bid_low',
                'bid_close',
                'ask_open',
                'ask_high',
                'ask_low',
                'ask_close',
            ])
            ->map(function (MarketPrice $price): MarketPrice {
                $price->open = $price->open ?? $this->midpoint($price->bid_open, $price->ask_open);
                $price->high = $price->high ?? $this->midpoint($price->bid_high, $price->ask_high);
                $price->low = $price->low ?? $this->midpoint($price->bid_low, $price->ask_low);
                $price->close = $price->close ?? $this->midpoint($price->bid_close, $price->ask_close);

                return $price;
            });
    }

    private function midpoint(string|int|float|null $bid, string|int|float|null $ask): ?float
    {
        if (! is_numeric($bid) || ! is_numeric($ask)) {
            return null;
        }

        return ((float) $bid + (float) $ask) / 2;
    }

    public function create(array $attributes): MarketPrice
    {
        return $this->model->newQuery()->create($attributes);
    }

    /**
     * Insert new candle timestamps and refresh existing ones for the same pair and timeframe.
     *
     * @param  list<array<string, mixed>>  $candles
     */
    public function importCandles(array $candles): int
    {
        $candlesByDate = [];
        foreach ($candles as $candle) {
            $candlesByDate[$candle['pair_id'].':'.$candle['timeframe_type_id'].':'.$candle['price_date']] = $candle;
        }

        $insertedOrUpdated = 0;
        foreach (array_chunk(array_values($candlesByDate), 500) as $chunk) {
            $first = $chunk[0];
            $existing = $this->model->newQuery()
                ->where('pair_id', $first['pair_id'])
                ->where('timeframe_type_id', $first['timeframe_type_id'])
                ->whereIn('price_date', array_column($chunk, 'price_date'))
                ->get(['id', 'price_date'])
                ->keyBy(fn (MarketPrice $price): string => $price->price_date->format('Y-m-d H:i:s'));

            $newRecords = [];
            foreach ($chunk as $candle) {
                $existingPrice = $existing->get((string) $candle['price_date']);
                if ($existingPrice !== null) {
                    $existingPrice->update($candle);
                } else {
                    $newRecords[] = $candle;
                }
                $insertedOrUpdated++;
            }

            foreach (array_chunk($newRecords, 100) as $newRecordChunk) {
                $this->model->newQuery()->insert($newRecordChunk);
            }
        }

        return $insertedOrUpdated;
    }

    public function update(int $id, array $attributes): MarketPrice
    {
        $record = $this->model->newQuery()->findOrFail($id);
        $record->update($attributes);

        return $record;
    }

    public function delete(int $id): ?bool
    {
        return $this->model->newQuery()->findOrFail($id)->delete();
    }
}
