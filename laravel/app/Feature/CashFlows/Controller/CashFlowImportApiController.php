<?php

declare(strict_types=1);

namespace App\Feature\CashFlows\Controller;

use App\Feature\CashFlows\Action\ReceiveCashFlowEventsAction;
use App\Feature\CashFlows\Request\ReceiveCashFlowEventsRequest;
use Illuminate\Http\JsonResponse;

class CashFlowImportApiController
{
    public function receive(
        ReceiveCashFlowEventsRequest $request,
        ReceiveCashFlowEventsAction $receiveEvents,
    ): JsonResponse {
        return response()->json($receiveEvents->execute(
            $request->activeSession(),
            $request->validated('events'),
        ));
    }
}
