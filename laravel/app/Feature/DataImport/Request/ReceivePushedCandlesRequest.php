<?php

declare(strict_types=1);

namespace App\Feature\DataImport\Request;

use App\Feature\DataImport\Action\FindActiveDataImportSessionAction;
use App\Feature\DataImport\Model\DataImportSession;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class ReceivePushedCandlesRequest extends FormRequest
{
    private ?DataImportSession $activeSession = null;

    public function authorize(FindActiveDataImportSessionAction $findSession): bool
    {
        $this->activeSession = $findSession->execute($this->bearerToken());

        return $this->activeSession !== null;
    }

    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(
            response()->json(['message' => 'No active import session matches this bearer token.'], 401),
        );
    }

    public function activeSession(): DataImportSession
    {
        if ($this->activeSession === null) {
            throw new AuthorizationException('An active import session is required.');
        }

        return $this->activeSession;
    }

    public function rules(): array
    {
        return [
            'candles' => ['required', 'array', 'min:1', 'max:1000'],
            'candles.*.time' => ['required', 'date_format:Y-m-d H:i:s', 'distinct'],
            'candles.*.open' => ['required', 'numeric', 'between:0,999999.999999'],
            'candles.*.high' => ['required', 'numeric', 'between:0,999999.999999'],
            'candles.*.low' => ['required', 'numeric', 'between:0,999999.999999'],
            'candles.*.close' => ['required', 'numeric', 'between:0,999999.999999'],
            'candles.*.bid_open' => ['nullable', 'numeric', 'between:0,999999.999999'],
            'candles.*.bid_high' => ['nullable', 'numeric', 'between:0,999999.999999'],
            'candles.*.bid_low' => ['nullable', 'numeric', 'between:0,999999.999999'],
            'candles.*.bid_close' => ['nullable', 'numeric', 'between:0,999999.999999'],
            'candles.*.ask_open' => ['nullable', 'numeric', 'between:0,999999.999999'],
            'candles.*.ask_high' => ['nullable', 'numeric', 'between:0,999999.999999'],
            'candles.*.ask_low' => ['nullable', 'numeric', 'between:0,999999.999999'],
            'candles.*.ask_close' => ['nullable', 'numeric', 'between:0,999999.999999'],
        ];
    }
}
