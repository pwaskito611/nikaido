<?php

namespace App\Engine\Controllers;

use App\Engine\Actions\RunSpecificPairBacktestAction;
use App\Engine\Requests\RunSpecificPairTestRequest;
use App\Engine\Resources\SpecificPairTestResultResource;
use App\Strategy\Strategy;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EngineController
{
    public function runSpecificPairTest(
        RunSpecificPairTestRequest $request,
        RunSpecificPairBacktestAction $runSpecificPairBacktest,
    ): AnonymousResourceCollection {
        $results = $runSpecificPairBacktest->execute(
            $request->toConfig(),
            new Strategy,
        );

        return SpecificPairTestResultResource::collection($results->toArray());
    }
}
