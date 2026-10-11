<?php

namespace App\Engine\Repositories;

use App\Engine\Models\MarketType;

class MarketTypeRepository
{
    public function __construct(
        private readonly MarketType $model,
    ) {}

    public function find(int $id): ?MarketType
    {
        return $this->model->newQuery()->find($id);
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
