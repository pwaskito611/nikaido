<?php

declare(strict_types=1);

namespace App\Feature\DataImport\Controller;

use App\Feature\DataImport\Action\ImportExternalProviderCandlesAction;
use App\Feature\DataImport\Action\LoadDataImportPageAction;
use App\Feature\DataImport\Action\StartIncomingDataSessionAction;
use App\Feature\DataImport\Action\StopIncomingDataSessionAction;
use App\Feature\DataImport\Request\ExternalProviderImportRequest;
use App\Feature\DataImport\Request\StartIncomingDataSessionRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DataImportController
{
    public function index(LoadDataImportPageAction $loadPage): View
    {
        return view('workbench.data-import.index', $loadPage->execute());
    }

    public function startIncoming(
        StartIncomingDataSessionRequest $request,
        StartIncomingDataSessionAction $startSession,
    ): RedirectResponse {
        $input = $request->validated();
        $result = $startSession->execute((int) $input['pair_id'], (int) $input['timeframe_type_id']);

        return redirect()->route('workbench.data-import.index')
            ->with('status', 'Incoming import session started. Copy the bearer token now; it is only shown once.')
            ->with('data_import_token', $result->token)
            ->with('data_import_session_id', $result->session->id);
    }

    public function stopIncoming(int $sessionId, StopIncomingDataSessionAction $stopSession): RedirectResponse
    {
        $stopSession->execute($sessionId);

        return redirect()->route('workbench.data-import.index')
            ->with('status', 'Incoming import session stopped. Its token can no longer submit data.');
    }

    public function importExternalProvider(
        ExternalProviderImportRequest $request,
        ImportExternalProviderCandlesAction $importCandles,
    ): RedirectResponse {
        $input = $request->validated();
        $count = $importCandles->execute(
            $input['external_provider_credential'],
            (int) $input['pair_id'],
            (int) $input['timeframe_type_id'],
        );

        return redirect()->route('workbench.data-import.index')
            ->with('status', "External provider import complete: {$count} candles inserted or updated.");
    }
}
