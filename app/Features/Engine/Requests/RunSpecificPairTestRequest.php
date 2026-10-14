<?php

declare(strict_types=1);

namespace App\Features\Engine\Requests;

use App\Features\Engine\Config\FixedSlippageConfig;
use App\Features\Engine\Config\PartialFillConfig;
use App\Features\Engine\Config\SpecificPairTestConfig;
use App\Features\Engine\Config\UnfilledOrderPolicy;
use DateTimeImmutable;
use Exact\Collection\Vec;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class RunSpecificPairTestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'base' => ['required', 'string', 'max:10'],
            'quote' => ['required', 'string', 'max:10'],
            'from' => ['required', 'date_format:Y-m-d H:i:s'],
            'to' => ['required', 'date_format:Y-m-d H:i:s', 'after_or_equal:from'],
            'active_from' => ['present', $this->activeTimeRule()],
            'active_until' => ['present', $this->activeTimeRule()],
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

    public function toConfig(): SpecificPairTestConfig
    {
        $validated = $this->validated();

        return new SpecificPairTestConfig(
            base: strtoupper($validated['base']),
            quote: strtoupper($validated['quote']),
            from: new DateTimeImmutable($validated['from']),
            to: new DateTimeImmutable($validated['to']),
            activeFrom: $validated['active_from'],
            activeUntil: $validated['active_until'],
            excludedDays: Vec::fromArray(array_map('intval', $validated['excluded_days'] ?? [])),
            slippage: new FixedSlippageConfig(
                enabled: $this->boolean('slippage_enabled'),
                pips: (float) ($validated['slippage_pips'] ?? 0),
                pipSize: isset($validated['slippage_pip_size']) ? (float) $validated['slippage_pip_size'] : null,
            ),
            partialFill: new PartialFillConfig(
                enabled: $this->boolean('partial_fill_enabled'),
                ratio: (float) ($validated['partial_fill_ratio'] ?? 1),
                unfilledOrderPolicy: UnfilledOrderPolicy::from($validated['unfilled_order_policy'] ?? UnfilledOrderPolicy::Keep->value),
            ),
        );
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
