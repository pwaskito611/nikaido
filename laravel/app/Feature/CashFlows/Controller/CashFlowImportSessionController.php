<?php

declare(strict_types=1);

namespace App\Feature\CashFlows\Controller;

use App\Feature\CashFlows\Action\StartCashFlowImportSessionAction;
use App\Feature\CashFlows\Action\StopCashFlowImportSessionAction;
use App\Feature\CashFlows\Request\CashFlowImportSessionRequest;
use Illuminate\Http\RedirectResponse;

class CashFlowImportSessionController
{
    public function start(
        CashFlowImportSessionRequest $request,
        StartCashFlowImportSessionAction $startSession,
    ): RedirectResponse {
        $result = $startSession->execute((int) $request->validated('market_pair_id'));

        return redirect()->route('workbench.data-import.index')
            ->with('status', 'Cash flow receiver started. Copy the bearer token now; it is only shown once.')
            ->with('cash_flow_import_token', $result->token)
            ->with('cash_flow_import_session_id', $result->session->id);
    }

    public function stop(
        int $marketPair,
        int $sessionId,
        StopCashFlowImportSessionAction $stopSession,
    ): RedirectResponse {
        $stopSession->execute($marketPair, $sessionId);

        return redirect()->route('workbench.data-import.index')
            ->with('status', 'Cash flow receiver stopped. Its token can no longer submit data.');
    }
}
