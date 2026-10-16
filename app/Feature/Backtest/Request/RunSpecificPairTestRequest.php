<?php

declare(strict_types=1);

namespace App\Feature\Backtest\Request;

use App\Feature\Backtest\Config\FixedSlippageConfig;
use App\Feature\Backtest\Config\PartialFillConfig;
use App\Feature\Backtest\Config\SpecificPairTestConfig;
use App\Feature\Backtest\Config\TradeCostConfig;
use App\Feature\Backtest\Config\UnfilledOrderPolicy;
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
            'trade_costs' => ['sometimes', 'array'],
            'trade_costs.enabled' => ['sometimes', 'boolean'],
            ...$this->tradeCostRules(),
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

            if (filter_var($this->input('trade_costs.transaction_tax.enabled', false), FILTER_VALIDATE_BOOLEAN)
                && ! in_array($this->input('trade_costs.transaction_tax.applies_to'), ['both', 'entry', 'exit'], true)) {
                $validator->errors()->add(
                    'trade_costs.transaction_tax.applies_to',
                    'Choose whether the transaction tax applies to entry, exit, or both.',
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
            tradeCosts: TradeCostConfig::fromArray($validated['trade_costs'] ?? []),
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

    /** @return array<string, array<int, mixed>> */
    private function tradeCostRules(): array
    {
        $rules = [];
        foreach ([
            'commission', 'exchange_fee', 'clearing_fee', 'regulatory_fee', 'transaction_tax',
        ] as $component) {
            $prefix = "trade_costs.{$component}";
            $rules[$prefix] = ['sometimes', 'array'];
            $rules["{$prefix}.enabled"] = ['sometimes', 'boolean'];
            $rules["{$prefix}.applies_to"] = $component === 'transaction_tax'
                ? ["required_if:{$prefix}.enabled,1", 'nullable', 'in:both,entry,exit']
                : ['sometimes', 'in:both,entry,exit'];
            $rules["{$prefix}.method"] = [
                "required_if:{$prefix}.enabled,1",
                'nullable',
                'in:per_order,transaction_value_percent,per_quantity',
            ];
            $rules["{$prefix}.rate"] = ["required_if:{$prefix}.enabled,1", 'nullable', 'numeric', 'gte:0', 'max:1000000000'];
        }
        foreach (['borrow_fee', 'financing_fee'] as $component) {
            $prefix = "trade_costs.{$component}";
            $rules[$prefix] = ['sometimes', 'array'];
            $rules["{$prefix}.enabled"] = ['sometimes', 'boolean'];
            $rules["{$prefix}.applies_to"] = ['sometimes', 'in:both,entry,exit'];
            $rules["{$prefix}.method"] = ["required_if:{$prefix}.enabled,1", 'nullable', 'in:daily_notional_percent'];
            $rules["{$prefix}.rate"] = ["required_if:{$prefix}.enabled,1", 'nullable', 'numeric', 'gte:0', 'max:1000000000'];
        }

        return $rules;
    }
}
