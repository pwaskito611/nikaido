<?php

declare(strict_types=1);

namespace App\Feature\MarketPairs\Repository;

use App\Feature\MarketPairs\Data\MarketPairDefinition;
use App\Feature\MarketPairs\Data\MarketPairMarketData;
use App\Feature\MarketPairs\Data\MarketPairQuantityUnit;
use App\Feature\MarketPairs\Data\RelatedMarketPairMarketData;
use App\Feature\MarketPairs\Model\MarketPair;
use App\Feature\MarketPrice\Model\MarketPrice;
use DateTimeInterface;
use Exact\Collection\LazySeq;
use Exact\Collection\Vec;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use UnexpectedValueException;

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
            ->with(['marketType', 'relatedPairs'])
            ->withCount('prices')
            ->findOrFail($id);
    }

    public function lockForUpdate(int $id): MarketPair
    {
        return $this->model->newQuery()
            ->lockForUpdate()
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

    /** @return Vec<MarketPair> */
    public function all(): Vec
    {
        return Vec::fromArray($this->model->newQuery()
            ->with('marketType')
            ->orderBy('base')
            ->orderBy('quote')
            ->get()
            ->all());
    }

    public function hasPrices(int $id): bool
    {
        return $this->model->newQuery()
            ->findOrFail($id)
            ->prices()
            ->exists();
    }

    public function create(MarketPairDefinition $definition): MarketPair
    {
        if ($definition->relatedMarketPairs === null) {
            return $this->model->newQuery()->create($this->attributesFrom($definition));
        }

        return DB::transaction(function () use ($definition): MarketPair {
            $pair = $this->model->newQuery()->create($this->attributesFrom($definition));
            $this->syncRelatedPairs($pair, $definition->relatedMarketPairs ?? []);

            return $pair;
        });
    }

    public function update(int $id, MarketPairDefinition $definition): MarketPair
    {
        if ($definition->relatedMarketPairs === null) {
            $record = $this->model->newQuery()->findOrFail($id);
            $record->update($this->attributesFrom($definition));

            return $record;
        }

        return DB::transaction(function () use ($id, $definition): MarketPair {
            $record = $this->model->newQuery()->findOrFail($id);
            $record->update($this->attributesFrom($definition));
            $this->syncRelatedPairs($record, $definition->relatedMarketPairs ?? []);

            return $record;
        });
    }

    /**
     * @return array{market_type_id: int, base: string, quote: string, quantity_unit: string, contract_size: string}
     */
    private function attributesFrom(MarketPairDefinition $definition): array
    {
        return [
            'market_type_id' => $definition->marketTypeId,
            'base' => $definition->base,
            'quote' => $definition->quote,
            'quantity_unit' => $definition->quantityUnit->value,
            'contract_size' => $definition->contractSize,
        ];
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
        ?int $marketPairId = null,
    ): MarketPairMarketData {
        $pair = $this->model->newQuery()
            ->where('base', strtoupper($base))
            ->where('quote', strtoupper($quote))
            ->when($marketPairId !== null, fn ($query) => $query->whereKey($marketPairId))
            ->with('relatedPairs')
            ->orderBy('id')
            ->firstOrFail();

        $relatedMarketPairs = [];
        foreach ($pair->relatedPairs as $relatedPair) {
            $relatedTimeframeTypeId = $relatedPair->pivot->timeframe_type_id === null
                ? $timeframeTypeId
                : (int) $relatedPair->pivot->timeframe_type_id;
            $relatedMarketPairs[] = new RelatedMarketPairMarketData(
                marketPairId: (int) $relatedPair->id,
                base: $relatedPair->base,
                quote: $relatedPair->quote,
                timeframeTypeId: $relatedTimeframeTypeId,
                candles: $this->candlesBetween($relatedPair, $from, $to, $relatedTimeframeTypeId),
            );
        }
        $candles = $this->candlesBetween($pair, $from, $to, $timeframeTypeId);

        $quantityUnitValue = $pair->quantity_unit ?? MarketPairQuantityUnit::Units->value;
        $quantityUnit = MarketPairQuantityUnit::tryFrom($quantityUnitValue);
        if ($quantityUnit === null) {
            throw new UnexpectedValueException("Unsupported market pair quantity unit: {$quantityUnitValue}.");
        }
        $contractSize = is_numeric($pair->contract_size) ? (string) $pair->contract_size : '1';

        return new MarketPairMarketData(
            candles: $candles,
            quantityUnit: $quantityUnit,
            contractSize: $contractSize,
            marketPairId: (int) $pair->id,
            base: $pair->base,
            quote: $pair->quote,
            relatedMarketPairs: $relatedMarketPairs,
        );
    }

    /**
     * @return Vec<MarketPrice>
     */
    private function candlesBetween(
        MarketPair $pair,
        DateTimeInterface $from,
        DateTimeInterface $to,
        ?int $timeframeTypeId,
    ): Vec {
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

        return Vec::fromArray($warmupQuery->get()->all())
            ->reverse()
            ->concat(Vec::fromArray($pricesQuery->get()->all()));
    }

    /** @param array<int, int|null> $relatedMarketPairs */
    private function syncRelatedPairs(MarketPair $pair, array $relatedMarketPairs): void
    {
        if (array_key_exists((int) $pair->id, $relatedMarketPairs)
            && $relatedMarketPairs[(int) $pair->id] === null) {
            throw ValidationException::withMessages([
                'related_market_pair_ids' => 'A market pair requires an explicit timeframe when related to itself.',
            ]);
        }

        $syncData = [];
        foreach ($relatedMarketPairs as $relatedMarketPairId => $timeframeTypeId) {
            $syncData[$relatedMarketPairId] = ['timeframe_type_id' => $timeframeTypeId];
        }
        $pair->relatedPairs()->sync($syncData);
    }

    /**
     * @return LazySeq<MarketPair>
     */
    public function eligibleForMarketTypeBacktest(
        int $marketTypeId,
        int $timeframeTypeId,
        string $from,
        string $to,
        array $excludedPairIds = [],
    ): LazySeq {
        return LazySeq::from($this->model->newQuery()
            ->where('market_type_id', $marketTypeId)
            ->when($excludedPairIds !== [], fn ($query) => $query->whereNotIn('id', $excludedPairIds))
            ->whereHas('prices', static function ($query) use ($timeframeTypeId, $from, $to): void {
                $query->where('timeframe_type_id', $timeframeTypeId)
                    ->whereBetween('price_date', [$from, $to]);
            })
            ->select(['id', 'base', 'quote'])
            ->orderBy('id')
            ->lazyById(250));
    }
}
