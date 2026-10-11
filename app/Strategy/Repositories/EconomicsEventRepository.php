<?php

namespace App\Engine\Repositories;

use App\Engine\Models\EconomicsEvent;

class EconomicsEventRepository
{
    public function __construct(
        private readonly EconomicsEvent $model,
    ) {}

    public function find(int $id): ?EconomicsEvent
    {
        return $this->model->newQuery()->find($id);
    }

    public function create(array $attributes): EconomicsEvent
    {
        return $this->model->newQuery()->create($attributes);
    }

    public function update(int $id, array $attributes): EconomicsEvent
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
