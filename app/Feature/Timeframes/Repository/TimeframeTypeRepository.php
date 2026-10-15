<?php

declare(strict_types=1);

namespace App\Feature\Timeframes\Repository;

use App\Feature\Timeframes\Model\TimeframeType;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class TimeframeTypeRepository
{
    public function __construct(
        private readonly TimeframeType $model,
    ) {}

    public function find(int $id): ?TimeframeType
    {
        return $this->model->newQuery()->find($id);
    }

    public function findOrFail(int $id): TimeframeType
    {
        return $this->model->newQuery()
            ->withCount('marketPrices')
            ->findOrFail($id);
    }

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->model->newQuery()
            ->withCount('marketPrices')
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function all(): Collection
    {
        return $this->model->newQuery()->orderBy('name')->get();
    }

    public function hasMarketPrices(int $id): bool
    {
        return $this->model->newQuery()
            ->findOrFail($id)
            ->marketPrices()
            ->exists();
    }

    public function create(array $attributes): TimeframeType
    {
        return $this->model->newQuery()->create($attributes);
    }

    public function update(int $id, array $attributes): TimeframeType
    {
        $timeframe = $this->model->newQuery()->findOrFail($id);
        $timeframe->update($attributes);

        return $timeframe;
    }

    public function delete(int $id): ?bool
    {
        return $this->model->newQuery()->findOrFail($id)->delete();
    }
}
