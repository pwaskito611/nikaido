<?php

declare(strict_types=1);

namespace App\Features\Engine\Controllers;

use App\Features\Engine\Actions\RunSpecificPairBacktestAction;
use App\Features\Engine\Models\BacktestOrderHistoryEntry;
use App\Features\Engine\Requests\RunSpecificPairTestRequest;
use App\Features\Engine\Resources\SpecificPairTestResultResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EngineController
{
    public function runSpecificPairTest(
        RunSpecificPairTestRequest $request,
        RunSpecificPairBacktestAction $runSpecificPairBacktest,
    ): AnonymousResourceCollection {
        $backtest = $runSpecificPairBacktest->execute(
            $request->toConfig(),
        );

        return SpecificPairTestResultResource::collection(
            $backtest->decisions->toArray(),
        )->additional([
            'order_history' => $backtest->orders
                ->map(static fn (BacktestOrderHistoryEntry $order): array => $order->jsonSerialize())
                ->toArray(),
            'initial_capital' => $backtest->initialCapital,
            'ending_capital' => $backtest->endingCapital,
            'quantity_unit' => $backtest->quantityUnit,
            'units_per_lot' => $backtest->unitsPerLot,
            'quantity_multiplier' => $backtest->quantityMultiplier,
            'execution_config' => $backtest->executionConfiguration->toArray(),
        ]);
    }
}
