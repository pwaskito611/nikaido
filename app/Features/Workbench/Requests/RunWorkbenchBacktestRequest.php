<?php

declare(strict_types=1);

namespace App\Features\Workbench\Requests;

use App\Features\Engine\Config\ConfiguredPairBacktestInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class RunWorkbenchBacktestRequest extends FormRequest
{
    public function toConfiguredPairBacktestInput(): ConfiguredPairBacktestInput
    {
        return ConfiguredPairBacktestInput::fromArray($this->validated());
    }

    protected function prepareForValidation(): void
    {
        $from = $this->normalizeDateTime($this->input('from'));
        $to = $this->normalizeDateTime($this->input('to'));

        $this->merge([
            'test_type' => $this->input('test_type', 'specific_pair'),
            'capital_mode' => $this->input('test_type', 'specific_pair') === 'market_type'
                ? $this->input('capital_mode', 'shared')
                : null,
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
            'test_type' => ['required', 'in:specific_pair,market_type'],
            'market_pair_id' => ['required_if:test_type,specific_pair', 'nullable', 'integer', 'exists:market_pair,id'],
            'market_type_id' => ['required_if:test_type,market_type', 'nullable', 'integer', 'exists:market_type,id'],
            'capital_mode' => ['required_if:test_type,market_type', 'nullable', 'in:shared,separate'],
            'excluded_pair_ids' => ['sometimes', 'array'],
            'excluded_pair_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('market_pair', 'id')->where('market_type_id', $this->input('market_type_id')),
            ],
            'timeframe_type_id' => ['required', 'integer', 'exists:timeframe_type,id'],
            'initial_capital' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
            'from' => ['required', 'date_format:Y-m-d H:i:s'],
            'to' => ['required', 'date_format:Y-m-d H:i:s', 'after_or_equal:from'],
            'active_from' => ['present', $this->activeTimeRule()],
            'active_until' => ['present', $this->activeTimeRule()],
            'full_day' => ['sometimes', 'boolean'],
            'excluded_days' => ['sometimes', 'array'],
            'excluded_days.*' => ['integer', 'between:1,7'],
            'slippage_enabled' => ['sometimes', 'boolean'],
            'slippage_pips' => ['required_if:slippage_enabled,1', 'nullable', 'numeric', 'gte:0'],
            'slippage_pip_size' => ['nullable', 'numeric', 'gt:0'],
            'partial_fill_enabled' => ['sometimes', 'boolean'],
            'partial_fill_ratio' => ['required_if:partial_fill_enabled,1', 'nullable', 'numeric', 'gt:0', 'lte:1'],
            'unfilled_order_policy' => ['sometimes', 'in:keep,cancel'],
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
