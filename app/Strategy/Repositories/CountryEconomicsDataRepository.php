<?php

namespace App\Engine\Repositories;

use App\Engine\Models\CountryEconomicsData;

class CountryEconomicsDataRepository
{
    public function __construct(
        private readonly CountryEconomicsData $model,
    ) {}

    public function find(int $id): ?CountryEconomicsData
    {
        return $this->model->newQuery()->find($id);
    }

    public function create(array $attributes): CountryEconomicsData
    {
        return $this->model->newQuery()->create($attributes);
    }

    public function update(int $id, array $attributes): CountryEconomicsData
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
