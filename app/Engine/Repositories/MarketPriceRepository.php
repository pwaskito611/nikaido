<?php

declare(strict_types=1);

namespace App\Engine\Repositories;

use App\Engine\Models\MarketPrice;

class MarketPriceRepository
{
    public function __construct(
        private readonly MarketPrice $model,
    ) {}

    public function find(int $id): ?MarketPrice
    {
        return $this->model->newQuery()->find($id);
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
