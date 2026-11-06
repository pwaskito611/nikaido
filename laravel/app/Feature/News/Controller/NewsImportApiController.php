<?php

declare(strict_types=1);

namespace App\Feature\News\Controller;

use App\Feature\News\Action\ReceiveNewsAction;
use App\Feature\News\Request\ReceiveNewsRequest;
use Illuminate\Http\JsonResponse;

class NewsImportApiController
{
    public function __invoke(ReceiveNewsRequest $request, ReceiveNewsAction $receiveNews): JsonResponse
    {
        $result = $receiveNews->execute(
            $request->activeSession(),
            $request->validated('articles'),
        );

        return response()->json(['data' => $result], 202);
    }
}
