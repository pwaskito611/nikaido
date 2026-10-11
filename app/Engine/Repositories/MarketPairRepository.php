<?php

declare(strict_types=1);

namespace App\Engine\Repositories;

use App\Engine\Models\MarketPair;
use DateTimeInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

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
    ): MarketPair {
        $pair = $this->model->newQuery()
            ->where('base', strtoupper($base))
            ->where('quote', strtoupper($quote))
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
}
