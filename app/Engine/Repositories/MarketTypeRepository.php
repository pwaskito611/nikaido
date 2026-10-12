<?php

declare(strict_types=1);

namespace App\Engine\Repositories;

use App\Engine\Models\MarketType;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

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

    /**
     * @return Collection<int, MarketType>
     */
    public function all(): Collection
    {
        return $this->model->newQuery()
            ->orderBy('name')
            ->get();
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
