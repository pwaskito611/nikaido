<?php

declare(strict_types=1);

namespace App\Feature\News\Controller;

use App\Feature\News\Action\StartNewsImportSessionAction;
use App\Feature\News\Action\StopNewsImportSessionAction;
use App\Feature\News\Request\StartNewsImportSessionRequest;
use Illuminate\Http\RedirectResponse;

class NewsImportSessionController
{
    public function start(
        StartNewsImportSessionRequest $request,
        StartNewsImportSessionAction $startSession,
    ): RedirectResponse {
        $result = $startSession->execute((int) $request->validated('market_pair_id'));

        return redirect()->route('workbench.data-import.index')
            ->with('status', 'News import session started. Copy the bearer token now; it is only shown once.')
            ->with('news_import_token', $result->token);
    }

    public function stop(int $sessionId, StopNewsImportSessionAction $stopSession): RedirectResponse
    {
        $stopSession->execute($sessionId);

        return redirect()->route('workbench.data-import.index')
            ->with('status', 'News import session stopped. Its token can no longer submit data.');
    }
}
