<?php

declare(strict_types=1);

namespace App\Feature\CashFlows\Request;

use App\Feature\CashFlows\Action\FindActiveCashFlowImportSessionAction;
use App\Feature\CashFlows\Model\CashFlowImportSession;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ReceiveCashFlowEventsRequest extends FormRequest
{
    private ?CashFlowImportSession $activeSession = null;

    public function authorize(FindActiveCashFlowImportSessionAction $findSession): bool
    {
        $this->activeSession = $findSession->execute($this->bearerToken());

        return $this->activeSession !== null;
    }

    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(
            response()->json(['message' => 'No active cash flow import session matches this bearer token.'], 401),
        );
    }

    public function activeSession(): CashFlowImportSession
    {
        if ($this->activeSession === null) {
            throw new AuthorizationException('An active cash flow import session is required.');
        }

        return $this->activeSession;
    }

    protected function prepareForValidation(): void
    {
        $events = $this->input('events');
        if (! is_array($events)) {
            return;
        }

        foreach ($events as $index => $event) {
            if (! is_array($event)) {
                continue;
            }
            if (is_string($event['event_type'] ?? null)) {
                $events[$index]['event_type'] = strtolower(trim($event['event_type']));
            }
            foreach (['announced_at', 'available_at', 'ex_date', 'record_date', 'payable_at'] as $field) {
                $value = $event[$field] ?? null;
                if (is_string($value)) {
                    $value = str_replace('T', ' ', $value);
                    if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $value) === 1) {
                        $value .= ':00';
                    }
                    $events[$index][$field] = $value;
                } else {
                    $events[$index][$field] = null;
                }
            }
            $events[$index]['status'] ??= 'announced';
            $events[$index]['source'] ??= null;
            $events[$index]['external_id'] ??= null;
            if (is_string($events[$index]['external_id'])) {
                $events[$index]['external_id'] = trim($events[$index]['external_id']) ?: null;
            }
        }

        $this->merge(['events' => $events]);
    }

    public function rules(): array
    {
        return [
            'events' => ['required', 'array', 'min:1', 'max:1000'],
            'events.*.event_type' => [
                'required',
                Rule::in(['dividend_adjustment']),
            ],
            'events.*.amount_per_unit' => [
                'required',
                'numeric',
                'gt:0',
                'decimal:0,12',
                'max:999999999999999.999999999999',
            ],
            'events.*.announced_at' => ['nullable', 'date_format:Y-m-d H:i:s'],
            'events.*.available_at' => ['required', 'date_format:Y-m-d H:i:s'],
            'events.*.ex_date' => ['nullable', 'date_format:Y-m-d H:i:s'],
            'events.*.record_date' => ['nullable', 'date_format:Y-m-d H:i:s'],
            'events.*.payable_at' => ['nullable', 'date_format:Y-m-d H:i:s'],
            'events.*.status' => ['required', Rule::in(['announced', 'confirmed', 'paid', 'cancelled'])],
            'events.*.source' => ['nullable', 'string', 'max:150'],
            'events.*.external_id' => ['nullable', 'string', 'max:191'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (strlen($this->getContent()) > 5_000_000) {
                $validator->errors()->add('events', 'The request payload must not exceed 5 MB.');

                return;
            }

            $events = $this->input('events');
            if (! is_array($events)) {
                return;
            }

            $seen = [];
            foreach ($events as $index => $event) {
                if (! is_array($event)) {
                    continue;
                }
                $identity = is_string($event['external_id'] ?? null) && $event['external_id'] !== ''
                    ? 'id:'.$event['external_id']
                    : hash('sha256', json_encode($event, JSON_PARTIAL_OUTPUT_ON_ERROR) ?: '');
                if (isset($seen[$identity])) {
                    $validator->errors()->add(
                        "events.{$index}.external_id",
                        'The same cash flow event is duplicated in this batch.',
                    );
                }
                $seen[$identity] = true;
            }
        });
    }
}
