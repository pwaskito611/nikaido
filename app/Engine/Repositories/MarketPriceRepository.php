<?php

declare(strict_types=1);

namespace App\Engine\Repositories;

use App\Engine\Models\MarketPrice;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

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

    public function create(array $attributes): MarketPrice
    {
        return $this->model->newQuery()->create($attributes);
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
