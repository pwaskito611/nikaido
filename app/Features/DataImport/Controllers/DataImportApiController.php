<?php

declare(strict_types=1);

namespace App\Features\DataImport\Controllers;

use App\Features\DataImport\Actions\ReceivePushedCandlesAction;
use App\Features\DataImport\Requests\ReceivePushedCandlesRequest;
use Illuminate\Http\JsonResponse;

class DataImportApiController
{
    public function receive(
        ReceivePushedCandlesRequest $request,
        ReceivePushedCandlesAction $receiveCandles,
    ): JsonResponse {
        $count = $receiveCandles->execute($request->activeSession(), $request->validated('candles'));

        return response()->json(['imported' => $count]);
    }
}
