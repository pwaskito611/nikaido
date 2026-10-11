<?php

namespace App\Engine\Repositories;

use App\Engine\Models\MarketPair;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketPairRepository
{
    public function __construct(
        private readonly MarketPair $model,
    ) {}

    public function find(int $id): ?MarketPair
    {
        return $this->model->newQuery()->find($id);
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
    ): MarketPair {
        return $this->model->newQuery()
            ->where('base', strtoupper($base))
            ->where('quote', strtoupper($quote))
            ->with(['prices' => function (HasMany $query) use ($from, $to): void {
                $query->whereBetween('price_date', [$from, $to])
                    ->orderBy('price_date')
                    ->orderBy('id');
            }])
            ->orderBy('id')
            ->firstOrFail();
    }
}
