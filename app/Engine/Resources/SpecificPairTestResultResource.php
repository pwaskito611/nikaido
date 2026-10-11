<?php

namespace App\Engine\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SpecificPairTestResultResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'candle' => $this->resource['candle']->toArray(),
            'result' => $this->resource['result'],
            'signal' => $this->resource['signal'],
            'stop_loss' => $this->resource['stop_loss'],
            'take_profit' => $this->resource['take_profit'],
        ];
    }
}
