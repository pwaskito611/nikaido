<?php

declare(strict_types=1);

namespace App\Feature\CashFlows\Repository;

use App\Feature\CashFlows\Model\MarketPairCashFlow;
use DateTimeInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Nikaido\Strategy\Models\CashFlowDataCollection;
use Nikaido\Strategy\Models\CashFlowEventData;

class MarketPairCashFlowRepository
{
    public function paginate(?int $marketPairId = null, int $perPage = 25): LengthAwarePaginator
    {
        return MarketPairCashFlow::query()
            ->with('marketPair')
            ->when($marketPairId !== null, fn ($query) => $query->where('market_pair_id', $marketPairId))
            ->orderByDesc('available_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function findOrFail(int $id): MarketPairCashFlow
    {
        return MarketPairCashFlow::query()->with('marketPair')->findOrFail($id);
    }

    public function findForPairOrFail(int $pairId, int $id): MarketPairCashFlow
    {
        return MarketPairCashFlow::query()
            ->where('market_pair_id', $pairId)
            ->findOrFail($id);
    }

    public function create(array $attributes): MarketPairCashFlow
    {
        return MarketPairCashFlow::query()->create($attributes);
    }

    public function update(int $id, array $attributes): MarketPairCashFlow
    {
        $cashFlow = MarketPairCashFlow::query()->findOrFail($id);
        $cashFlow->update($attributes);

        return $cashFlow;
    }

    public function delete(int $id): void
    {
        MarketPairCashFlow::query()->findOrFail($id)->delete();
    }

    /**
     * @param  list<array<string, mixed>>  $events
     * @return array{imported: int, inserted: int, updated: int}
     */
    public function import(int $marketPairId, array $events): array
    {
        return DB::transaction(function () use ($marketPairId, $events): array {
            $inserted = 0;
            $updated = 0;

            foreach ($events as $event) {
                $existing = isset($event['external_id'])
                    ? MarketPairCashFlow::query()
                        ->where('market_pair_id', $marketPairId)
                        ->where('external_id', $event['external_id'])
                        ->first()
                    : MarketPairCashFlow::query()
                        ->where('market_pair_id', $marketPairId)
                        ->where('event_type', $event['event_type'])
                        ->where('amount_per_unit', $event['amount_per_unit'])
                        ->where('announced_at', $event['announced_at'])
                        ->where('available_at', $event['available_at'])
                        ->where('ex_date', $event['ex_date'])
                        ->where('record_date', $event['record_date'])
                        ->where('payable_at', $event['payable_at'])
                        ->where('source', $event['source'])
                        ->first();

                if ($existing === null) {
                    MarketPairCashFlow::query()->create([
                        'market_pair_id' => $marketPairId,
                        ...$event,
                    ]);
                    $inserted++;

                    continue;
                }

                $existing->update($event);
                $updated++;
            }

            return ['imported' => count($events), 'inserted' => $inserted, 'updated' => $updated];
        });
    }

    public function hasForMarketPair(int $marketPairId): bool
    {
        return MarketPairCashFlow::query()->where('market_pair_id', $marketPairId)->exists();
    }

    public function forBacktest(
        int $marketPairId,
        DateTimeInterface $from,
        DateTimeInterface $to,
    ): CashFlowDataCollection {
        $events = MarketPairCashFlow::query()
            ->where('market_pair_id', $marketPairId)
            ->whereNotNull('ex_date')
            ->whereBetween('ex_date', [$from, $to])
            ->where('available_at', '<=', $to)
            ->where('status', '!=', 'cancelled')
            ->orderBy('ex_date')
            ->orderBy('id')
            ->get();

        return new CashFlowDataCollection($events->map(
            static fn (MarketPairCashFlow $event): CashFlowEventData => new CashFlowEventData(
                id: (int) $event->id,
                eventType: $event->event_type,
                amountPerUnit: (float) $event->amount_per_unit,
                availableAt: \DateTimeImmutable::createFromInterface($event->available_at),
                announcedAt: $event->announced_at === null
                    ? null
                    : \DateTimeImmutable::createFromInterface($event->announced_at),
                exDate: \DateTimeImmutable::createFromInterface($event->ex_date),
                recordDate: $event->record_date === null
                    ? null
                    : \DateTimeImmutable::createFromInterface($event->record_date),
                payableAt: $event->payable_at === null
                    ? null
                    : \DateTimeImmutable::createFromInterface($event->payable_at),
                status: $event->status,
                source: $event->source,
            ),
        )->all());
    }
}
