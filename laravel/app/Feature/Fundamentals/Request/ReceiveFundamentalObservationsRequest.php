<?php

declare(strict_types=1);

namespace App\Feature\Fundamentals\Request;

use App\Feature\Fundamentals\Action\FindActiveFundamentalImportSessionAction;
use App\Feature\Fundamentals\Model\FundamentalImportSession;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Validator;
use JsonException;

class ReceiveFundamentalObservationsRequest extends FormRequest
{
    private ?FundamentalImportSession $activeSession = null;

    public function authorize(FindActiveFundamentalImportSessionAction $findSession): bool
    {
        $this->activeSession = $findSession->execute($this->bearerToken());

        return $this->activeSession !== null;
    }

    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(
            response()->json(['message' => 'No active fundamental import session matches this bearer token.'], 401),
        );
    }

    public function activeSession(): FundamentalImportSession
    {
        if ($this->activeSession === null) {
            throw new AuthorizationException('An active fundamental import session is required.');
        }

        return $this->activeSession;
    }

    protected function prepareForValidation(): void
    {
        $observations = $this->input('observations');
        if (! is_array($observations)) {
            return;
        }

        foreach ($observations as $index => $observation) {
            if (! is_array($observation)) {
                continue;
            }

            if (isset($observation['code']) && is_string($observation['code'])) {
                $observations[$index]['code'] = strtoupper(trim($observation['code']));
            }
            $observations[$index]['released_at'] ??= null;
        }

        $this->merge(['observations' => $observations]);
    }

    public function rules(): array
    {
        return [
            'observations' => ['required', 'array', 'min:1', 'max:1000'],
            'observations.*.code' => ['required', 'string', 'max:64', 'regex:/^[A-Z][A-Z0-9_]*$/D'],
            'observations.*.name' => ['required', 'string', 'max:150'],
            'observations.*.datetime' => ['required', 'date_format:Y-m-d H:i:s'],
            'observations.*.released_at' => ['sometimes', 'nullable', 'date_format:Y-m-d H:i:s'],
            'observations.*.value' => ['present'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (strlen($this->getContent()) > 5_000_000) {
                $validator->errors()->add('observations', 'The request payload must not exceed 5 MB.');

                return;
            }

            $observations = $this->input('observations');
            if (! is_array($observations)) {
                return;
            }

            $seen = [];
            foreach ($observations as $index => $observation) {
                if (! is_array($observation)
                    || ! is_string($observation['code'] ?? null)
                    || ! is_string($observation['datetime'] ?? null)) {
                    continue;
                }

                $key = $observation['code'].'|'.$observation['datetime'];
                if (isset($seen[$key])) {
                    $validator->errors()->add(
                        "observations.{$index}.datetime",
                        'The observation period is duplicated in this batch for the same indicator.',
                    );
                }
                $seen[$key] = true;

                if (! array_key_exists('value', $observation)) {
                    continue;
                }

                try {
                    $encodedValue = json_encode(
                        $observation['value'],
                        JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
                    );
                } catch (JsonException) {
                    $validator->errors()->add(
                        "observations.{$index}.value",
                        'The observation value must be valid JSON data.',
                    );

                    continue;
                }

                if (strlen($encodedValue) > 65_536) {
                    $validator->errors()->add(
                        "observations.{$index}.value",
                        'The observation value must not exceed 64 KB.',
                    );
                }
            }
        });
    }
}
