<?php

declare(strict_types=1);

namespace App\Feature\Backtest\Resource;

use App\Feature\Backtest\Data\BacktestDecision;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use UnexpectedValueException;

/** @mixin BacktestDecision */
class SpecificPairTestResultResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $decision = $this->resource;
        if (! $decision instanceof BacktestDecision) {
            throw new UnexpectedValueException('A specific-pair resource requires a backtest decision.');
        }

        return [
            'candle' => $decision->candle->toArray(),
            'result' => $decision->entered,
            'signal' => $decision->signal->value,
            'quantity' => $decision->quantity->getOrNull(),
            'quantity_unit' => $decision->quantityUnit,
            'stop_loss' => $decision->stopLoss->getOrNull(),
            'take_profit' => $decision->takeProfit->getOrNull(),
        ];
    }
}
