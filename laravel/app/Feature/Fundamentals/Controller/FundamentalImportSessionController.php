<?php

declare(strict_types=1);

namespace App\Feature\Fundamentals\Controller;

use App\Feature\Fundamentals\Action\StartFundamentalImportSessionAction;
use App\Feature\Fundamentals\Action\StopFundamentalImportSessionAction;
use App\Feature\Fundamentals\Request\StartFundamentalImportSessionRequest;
use Illuminate\Http\RedirectResponse;

class FundamentalImportSessionController
{
    public function start(
        StartFundamentalImportSessionRequest $request,
        StartFundamentalImportSessionAction $startSession,
    ): RedirectResponse {
        $result = $startSession->execute((int) $request->validated('fundamental_id'));

        return redirect()->route('workbench.data-import.index')
            ->with('status', 'Fundamental import session started. Copy the bearer token now; it is only shown once.')
            ->with('fundamental_import_token', $result->token)
            ->with('fundamental_import_session_id', $result->session->id);
    }

    public function stop(
        int $fundamental,
        int $sessionId,
        StopFundamentalImportSessionAction $stopSession,
    ): RedirectResponse {
        $stopSession->execute($fundamental, $sessionId);

        return redirect()->route('workbench.data-import.index')
            ->with('status', 'Fundamental import session stopped. Its token can no longer submit data.');
    }
}
