<?php

declare(strict_types=1);

namespace App\Feature\DataImport\Controller;

use App\Feature\DataImport\Action\ReceivePushedCandlesAction;
use App\Feature\DataImport\Request\ReceivePushedCandlesRequest;
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
