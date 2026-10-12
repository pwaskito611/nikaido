<?php

declare(strict_types=1);

namespace App\Engine\Repositories;

use App\Engine\Models\MarketPair;
use DateTimeInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\LazyCollection;

class MarketPairRepository
{
    public function __construct(
        private readonly MarketPair $model,
    ) {}

    public function find(int $id): ?MarketPair
    {
        return $this->model->newQuery()->find($id);
    }

    public function findOrFail(int $id): MarketPair
    {
        return $this->model->newQuery()
            ->with(['marketType'])
            ->withCount('prices')
            ->findOrFail($id);
    }

    public function lockForUpdate(int $id): MarketPair
    {
        return $this->model->newQuery()
            ->lockForUpdate()
            ->findOrFail($id);
    }

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->model->newQuery()
            ->with(['marketType'])
            ->withCount('prices')
            ->orderBy('base')
            ->orderBy('quote')
            ->paginate($perPage);
    }

    public function all(): Collection
    {
        return $this->model->newQuery()
            ->with('marketType')
            ->orderBy('base')
            ->orderBy('quote')
            ->get();
    }

    public function hasPrices(int $id): bool
    {
        return $this->model->newQuery()
            ->findOrFail($id)
            ->prices()
            ->exists();
    }

    public function create(array $attributes): MarketPair
    {
        return $this->model->newQuery()->create($attributes);
    }

    public function update(int $id, array $attributes): MarketPair
    {
        $record = $this->model->newQuery()->findOrFail($id);
        $record->update($attributes);

        return $record;
    }

    public function delete(int $id): ?bool
    {
        return $this->model->newQuery()->findOrFail($id)->delete();
    }

    public function findWithPricesBetween(
        string $base,
        string $quote,
        DateTimeInterface $from,
        DateTimeInterface $to,
        ?int $timeframeTypeId = null,
        ?int $marketPairId = null,
    ): MarketPair {
        $pair = $this->model->newQuery()
            ->where('base', strtoupper($base))
            ->where('quote', strtoupper($quote))
            ->when($marketPairId !== null, fn ($query) => $query->whereKey($marketPairId))
            ->orderBy('id')
            ->firstOrFail();

        $warmupQuery = $pair->prices()
            ->where('price_date', '<', $from)
            ->orderByDesc('price_date')
            ->orderByDesc('id')
            ->limit(200);

        $pricesQuery = $pair->prices()
            ->whereBetween('price_date', [$from, $to])
            ->orderBy('price_date')
            ->orderBy('id');

        if ($timeframeTypeId !== null) {
            $warmupQuery->where('timeframe_type_id', $timeframeTypeId);
            $pricesQuery->where('timeframe_type_id', $timeframeTypeId);
        }

        $warmupPrices = $warmupQuery->get()->reverse()->values();
        $prices = $pricesQuery->get();

        $pair->setRelation('prices', $warmupPrices->concat($prices));

        return $pair;
    }

    /**
     * @return LazyCollection<int, MarketPair>
     */
    public function eligibleForMarketTypeBacktest(
        int $marketTypeId,
        int $timeframeTypeId,
        string $from,
        string $to,
    ): LazyCollection {
        return $this->model->newQuery()
            ->where('market_type_id', $marketTypeId)
            ->whereHas('prices', static function ($query) use ($timeframeTypeId, $from, $to): void {
                $query->where('timeframe_type_id', $timeframeTypeId)
                    ->whereBetween('price_date', [$from, $to]);
            })
            ->select(['id', 'base', 'quote'])
            ->orderBy('id')
            ->lazyById(250);
    }
}
