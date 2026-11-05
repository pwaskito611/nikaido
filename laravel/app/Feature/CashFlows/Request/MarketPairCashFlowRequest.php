<?php

declare(strict_types=1);

namespace App\Feature\CashFlows\Request;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MarketPairCashFlowRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $input = [];
        foreach (['announced_at', 'available_at', 'ex_date', 'record_date', 'payable_at'] as $field) {
            $value = $this->input($field);
            if (is_string($value)) {
                $value = str_replace('T', ' ', $value);
                if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $value) === 1) {
                    $value .= ':00';
                }
                $input[$field] = $value;
            }
        }

        if (is_string($this->input('event_type'))) {
            $input['event_type'] = strtolower(trim($this->input('event_type')));
        }
        if (is_string($this->input('external_id'))) {
            $input['external_id'] = trim($this->input('external_id')) ?: null;
        }
        if ($input !== []) {
            $this->merge($input);
        }
    }

    public function rules(): array
    {
        $marketPairId = $this->input('market_pair_id');

        return [
            'market_pair_id' => ['required', 'integer', 'exists:market_pair,id'],
            'event_type' => ['required', Rule::in(['dividend', 'interest', 'staking_reward', 'distribution', 'other'])],
            'amount_per_unit' => ['required', 'numeric', 'gt:0', 'decimal:0,12', 'max:999999999999999.999999999999'],
            'announced_at' => ['nullable', 'date_format:Y-m-d H:i:s'],
            'available_at' => ['required', 'date_format:Y-m-d H:i:s'],
            'ex_date' => ['nullable', 'date_format:Y-m-d H:i:s'],
            'record_date' => ['nullable', 'date_format:Y-m-d H:i:s'],
            'payable_at' => ['nullable', 'date_format:Y-m-d H:i:s'],
            'status' => ['required', Rule::in(['announced', 'confirmed', 'paid', 'cancelled'])],
            'source' => ['nullable', 'string', 'max:150'],
            'external_id' => [
                'nullable',
                'string',
                'max:191',
                Rule::unique('market_pair_cash_flows', 'external_id')
                    ->where(fn ($query) => $query->where('market_pair_id', $marketPairId))
                    ->ignore($this->route('cashFlow')),
            ],
        ];
    }
}
