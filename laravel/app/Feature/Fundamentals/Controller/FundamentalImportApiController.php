<?php

declare(strict_types=1);

namespace App\Feature\Fundamentals\Controller;

use App\Feature\Fundamentals\Action\ReceiveFundamentalObservationsAction;
use App\Feature\Fundamentals\Request\ReceiveFundamentalObservationsRequest;
use Illuminate\Http\JsonResponse;

class FundamentalImportApiController
{
    public function receive(
        ReceiveFundamentalObservationsRequest $request,
        ReceiveFundamentalObservationsAction $receiveObservations,
    ): JsonResponse {
        $result = $receiveObservations->execute(
            $request->activeSession(),
            $request->validated('observations'),
        );

        return response()->json($result);
    }
}
