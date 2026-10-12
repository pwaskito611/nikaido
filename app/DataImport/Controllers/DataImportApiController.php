<?php

declare(strict_types=1);

namespace App\DataImport\Controllers;

use App\DataImport\Actions\ReceivePushedCandlesAction;
use App\DataImport\Requests\ReceivePushedCandlesRequest;
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
