<?php

declare(strict_types=1);

namespace App\Feature\Backtest\Request;

use App\Feature\Backtest\Config\FixedSlippageConfig;
use App\Feature\Backtest\Config\PartialFillConfig;
use App\Feature\Backtest\Config\PartialFillMode;
use App\Feature\Backtest\Config\SlippageDirection;
use App\Feature\Backtest\Config\SlippageDistribution;
use App\Feature\Backtest\Config\SlippageMode;
use App\Feature\Backtest\Config\SpecificPairTestConfig;
use App\Feature\Backtest\Config\TradeCostConfig;
use App\Feature\Backtest\Config\UnfilledOrderPolicy;
use DateTimeImmutable;
use Exact\Collection\Vec;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class RunSpecificPairTestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $dynamicSlippageEnabled = $this->boolean('slippage_enabled')
            && $this->input('slippage_mode', SlippageMode::Fixed->value) === SlippageMode::Dynamic->value;
        $randomSlippageEnabled = $this->boolean('slippage_enabled')
            && $this->input('slippage_mode', SlippageMode::Fixed->value) === SlippageMode::Random->value;

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
            'slippage_mode' => ['sometimes', 'in:fixed,dynamic,random'],
            'slippage_direction' => ['sometimes', 'in:adverse_only,random'],
            'slippage_unit' => ['sometimes', 'in:pips'],
            'fixed_slippage' => ['sometimes', 'nullable', 'numeric', 'gte:0', 'max:1000000000'],
            'slippage_pips' => ['sometimes', 'nullable', 'numeric', 'gte:0', 'max:1000000000'],
            'slippage_pip_size' => ['nullable', 'numeric', 'gt:0'],
            'base_slippage' => [Rule::requiredIf($dynamicSlippageEnabled), 'nullable', 'numeric', 'gte:0', 'max:1000000000'],
            'volatility_factor' => [Rule::requiredIf($dynamicSlippageEnabled), 'nullable', 'numeric', 'gte:0', 'max:1000000000'],
            'volume_impact_factor' => [Rule::requiredIf($dynamicSlippageEnabled), 'nullable', 'numeric', 'gte:0', 'max:1000000000'],
            'spread_factor' => [Rule::requiredIf($dynamicSlippageEnabled), 'nullable', 'numeric', 'gte:0', 'max:1000000000'],
            'minimum_slippage' => [Rule::requiredIf($randomSlippageEnabled), 'nullable', 'numeric', 'gte:0', 'max:1000000000'],
            'maximum_slippage' => [
                Rule::requiredIf($dynamicSlippageEnabled || $randomSlippageEnabled),
                'nullable',
                'numeric',
                'gte:0',
                'max:1000000000',
            ],
            'distribution' => [Rule::requiredIf($randomSlippageEnabled), 'in:uniform,normal'],
            'mean' => [Rule::requiredIf($randomSlippageEnabled && $this->input('distribution') === 'normal'), 'nullable', 'numeric', 'gte:0', 'max:1000000000'],
            'standard_deviation' => [Rule::requiredIf($randomSlippageEnabled && $this->input('distribution') === 'normal'), 'nullable', 'numeric', 'gte:0', 'max:1000000000'],
            'random_seed' => ['nullable', 'integer', 'between:0,2147483647'],
            'partial_fill_enabled' => ['sometimes', 'boolean'],
            'partial_fill_mode' => ['sometimes', 'in:fixed,volume'],
            'partial_fill_ratio' => [
                Rule::requiredIf(
                    $this->boolean('partial_fill_enabled')
                    && $this->input('partial_fill_mode', PartialFillMode::Fixed->value) === PartialFillMode::Fixed->value,
                ),
                'nullable',
                'numeric',
                'gt:0',
                'lte:1',
            ],
            'max_participation_rate' => ['required_if:partial_fill_mode,volume', 'nullable', 'numeric', 'gte:0', 'lte:1'],
            'volume_source' => ['required_if:partial_fill_mode,volume', 'in:traded_volume'],
            'execution_volume_period' => ['required_if:partial_fill_mode,volume', 'in:candle'],
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
            $mode = $this->input('slippage_mode', SlippageMode::Fixed->value);
            if ($this->boolean('slippage_enabled') && $mode === SlippageMode::Fixed->value
                && $this->input('fixed_slippage', $this->input('slippage_pips')) === null) {
                $validator->errors()->add('fixed_slippage', 'Fixed slippage is required when fixed slippage is enabled.');
            }
            if ($mode === SlippageMode::Random->value
                && $this->input('minimum_slippage') !== null
                && $this->input('maximum_slippage') !== null
                && (float) $this->input('minimum_slippage') > (float) $this->input('maximum_slippage')) {
                $validator->errors()->add('minimum_slippage', 'Minimum slippage cannot exceed maximum slippage.');
            }
            if ($mode === SlippageMode::Random->value
                && $this->input('distribution') === 'normal'
                && $this->input('mean') !== null
                && $this->input('minimum_slippage') !== null
                && $this->input('maximum_slippage') !== null
                && ((float) $this->input('mean') < (float) $this->input('minimum_slippage')
                    || (float) $this->input('mean') > (float) $this->input('maximum_slippage'))) {
                $validator->errors()->add('mean', 'Normal slippage mean must be within its configured boundaries.');
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
                pips: (float) ($validated['fixed_slippage'] ?? $validated['slippage_pips'] ?? 0),
                pipSize: isset($validated['slippage_pip_size']) ? (float) $validated['slippage_pip_size'] : null,
                mode: SlippageMode::from($validated['slippage_mode'] ?? SlippageMode::Fixed->value),
                direction: SlippageDirection::from($validated['slippage_direction'] ?? SlippageDirection::AdverseOnly->value),
                baseSlippage: (float) ($validated['base_slippage'] ?? 0),
                volatilityFactor: (float) ($validated['volatility_factor'] ?? 0),
                volumeImpactFactor: (float) ($validated['volume_impact_factor'] ?? 0),
                spreadFactor: (float) ($validated['spread_factor'] ?? 0),
                maximumSlippage: (float) ($validated['maximum_slippage'] ?? 10),
                minimumSlippage: (float) ($validated['minimum_slippage'] ?? 0),
                distribution: SlippageDistribution::from($validated['distribution'] ?? SlippageDistribution::Uniform->value),
                mean: (float) ($validated['mean'] ?? 0),
                standardDeviation: (float) ($validated['standard_deviation'] ?? 1),
                randomSeed: isset($validated['random_seed']) ? (int) $validated['random_seed'] : null,
                unit: $validated['slippage_unit'] ?? 'pips',
            ),
            partialFill: new PartialFillConfig(
                enabled: $this->boolean('partial_fill_enabled'),
                ratio: (float) ($validated['partial_fill_ratio'] ?? 1),
                unfilledOrderPolicy: UnfilledOrderPolicy::from($validated['unfilled_order_policy'] ?? UnfilledOrderPolicy::Keep->value),
                mode: PartialFillMode::from($validated['partial_fill_mode'] ?? PartialFillMode::Fixed->value),
                maxParticipationRate: (float) ($validated['max_participation_rate'] ?? 0.1),
                volumeSource: $validated['volume_source'] ?? 'traded_volume',
                executionVolumePeriod: $validated['execution_volume_period'] ?? 'candle',
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
