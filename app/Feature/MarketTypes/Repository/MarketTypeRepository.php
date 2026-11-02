<?php

declare(strict_types=1);

namespace App\Feature\MarketTypes\Repository;

use App\Feature\MarketTypes\Model\MarketType;
use Exact\Collection\Vec;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class MarketTypeRepository
{
    public function __construct(
        private readonly MarketType $model,
    ) {}

    public function find(int $id): ?MarketType
    {
        return $this->model->newQuery()->find($id);
    }

    public function findOrFail(int $id): MarketType
    {
        return $this->model->newQuery()
            ->withCount('marketPairs')
            ->findOrFail($id);
    }

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->model->newQuery()
            ->withCount('marketPairs')
            ->orderBy('name')
            ->paginate($perPage);
    }

    /** @return Vec<MarketType> */
    public function all(): Vec
    {
        return Vec::fromArray($this->model->newQuery()
            ->orderBy('name')
            ->get()
            ->all());
    }

    public function hasMarketPairs(int $id): bool
    {
        return $this->model->newQuery()
            ->findOrFail($id)
            ->marketPairs()
            ->exists();
    }

    public function create(array $attributes): MarketType
    {
        return $this->model->newQuery()->create($attributes);
    }

    public function update(int $id, array $attributes): MarketType
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
