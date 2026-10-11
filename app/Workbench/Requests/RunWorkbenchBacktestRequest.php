<?php

declare(strict_types=1);

namespace App\Workbench\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class RunWorkbenchBacktestRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $from = $this->normalizeDateTime($this->input('from'));
        $to = $this->normalizeDateTime($this->input('to'));

        $this->merge([
            'from' => $from,
            'to' => $to,
            'active_from' => $this->boolean('full_day') ? false : $this->input('active_from'),
            'active_until' => $this->boolean('full_day') ? false : $this->input('active_until'),
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'market_pair_id' => ['required', 'integer', 'exists:market_pair,id'],
            'timeframe_type_id' => ['required', 'integer', 'exists:timeframe_type,id'],
            'initial_capital' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
            'risk_per_trade_percent' => ['required', 'numeric', 'gt:0', 'lte:100', 'decimal:0,2'],
            'from' => ['required', 'date_format:Y-m-d H:i:s'],
            'to' => ['required', 'date_format:Y-m-d H:i:s', 'after_or_equal:from'],
            'active_from' => ['present', $this->activeTimeRule()],
            'active_until' => ['present', $this->activeTimeRule()],
            'full_day' => ['sometimes', 'boolean'],
            'excluded_days' => ['sometimes', 'array'],
            'excluded_days.*' => ['integer', 'between:1,7'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $activeFrom = $this->input('active_from');
            $activeUntil = $this->input('active_until');

            if (($activeFrom === false) !== ($activeUntil === false)) {
                $validator->errors()->add(
                    'active_from',
                    'Both active time boundaries must be false for a full-day schedule.',
                );
            }
        });
    }

    private function normalizeDateTime(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $value = str_replace('T', ' ', $value);

        return preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $value) === 1
            ? $value.':00'
            : $value;
    }

    private function activeTimeRule(): \Closure
    {
        return static function (string $attribute, mixed $value, \Closure $fail): void {
            if ($value !== false && (! is_string($value) || preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $value) !== 1)) {
                $fail("The {$attribute} field must be a time in HH:MM format or false.");
            }
        };
    }
}
