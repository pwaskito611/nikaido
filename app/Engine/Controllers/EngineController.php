<?php

namespace App\Engine\Controllers;

use App\Engine\Actions\RunSpecificPairBacktestAction;
use App\Engine\Requests\RunSpecificPairTestRequest;
use App\Engine\Resources\SpecificPairTestResultResource;
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
            $backtest['results']->toArray(),
        )->additional([
            'order_history' => $backtest['order_history'],
        ]);
    }
}
