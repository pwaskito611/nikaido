<?php

declare(strict_types=1);

namespace App\Feature\MarketPairs\Request;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class MarketPairRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'base' => strtoupper(trim((string) $this->input('base'))),
            'quote' => strtoupper(trim((string) $this->input('quote'))),
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $pairId = $this->route('marketPair');
        $existingPair = $pairId === null
            ? null
            : DB::table('market_pair')->where('id', (int) $pairId)->first(['market_type_id', 'base', 'quote']);
        $identityUnchanged = $existingPair !== null
            && (int) $existingPair->market_type_id === (int) $this->input('market_type_id')
            && $existingPair->base === $this->input('base')
            && $existingPair->quote === $this->input('quote');
        $baseRules = ['required', 'string', 'max:10'];
        if (! $identityUnchanged) {
            $baseRules[] = Rule::unique('market_pair', 'base')
                ->where(fn ($query) => $query
                    ->where('market_type_id', $this->input('market_type_id'))
                    ->where('quote', $this->input('quote')))
                ->ignore($pairId);
        }

        return [
            'market_type_id' => ['required', 'integer', 'exists:market_type,id'],
            'related_market_pair_ids' => ['sometimes', 'array'],
            'related_market_pair_ids.*' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('market_pair', 'id'),
            ],
            'related_market_pairs_present' => ['sometimes', 'accepted'],
            'related_timeframes' => ['sometimes', 'array'],
            'related_timeframes.*' => ['nullable', 'integer', Rule::exists('timeframe_type', 'id')],
            'quantity_unit' => ['required', 'in:units,contract,lot'],
            'contract_size' => ['required_if:quantity_unit,contract,lot', 'nullable', 'numeric', 'gt:0', 'decimal:0,8'],
            'tick_size' => ['required', 'numeric', 'gt:0', 'decimal:0,10'],
            'tick_value' => ['required', 'numeric', 'gt:0', 'decimal:0,10'],
            'minimum_quantity' => ['required', 'numeric', 'gt:0', 'decimal:0,8'],
            'quantity_step' => ['required', 'numeric', 'gt:0', 'decimal:0,8'],
            'initial_margin_rate' => ['required', 'numeric', 'gt:0', 'lte:100', 'decimal:0,4'],
            'base' => $baseRules,
            'quote' => ['required', 'string', 'max:10'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator): void {
            $primaryMarketPairId = (int) $this->route('marketPair');
            if ($primaryMarketPairId <= 0) {
                return;
            }

            $relatedMarketPairIds = $this->input('related_market_pair_ids', []);
            if (! is_array($relatedMarketPairIds)) {
                return;
            }

            foreach ($relatedMarketPairIds as $index => $relatedMarketPairId) {
                if ((int) $relatedMarketPairId !== $primaryMarketPairId) {
                    continue;
                }

                $timeframeTypeId = $this->input("related_timeframes.{$relatedMarketPairId}");
                if ($timeframeTypeId === null || $timeframeTypeId === '') {
                    $validator->errors()->add(
                        "related_market_pair_ids.{$index}",
                        'Choose an explicit timeframe when relating this pair to itself.',
                    );
                }
            }
        });
    }
}
