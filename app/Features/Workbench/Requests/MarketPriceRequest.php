<?php

declare(strict_types=1);

namespace App\Features\Workbench\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MarketPriceRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $priceDate = $this->input('price_date');
        if (is_string($priceDate)) {
            $priceDate = str_replace('T', ' ', $priceDate);
            if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $priceDate) === 1) {
                $priceDate .= ':00';
            }
        }

        $this->merge(['price_date' => $priceDate]);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'pair_id' => ['required', 'integer', 'exists:market_pair,id'],
            'timeframe_type_id' => ['required', 'integer', 'exists:timeframe_type,id'],
            'price_date' => [
                'required',
                'date_format:Y-m-d H:i:s',
                Rule::unique('market_price', 'price_date')
                    ->where(fn ($query) => $query
                        ->where('pair_id', $this->input('pair_id'))
                        ->where('timeframe_type_id', $this->input('timeframe_type_id')))
                    ->ignore($this->route('marketPrice')),
            ],
            'close' => ['required', 'numeric', 'gt:0', 'decimal:0,6'],
        ];

        foreach (['open', 'high', 'low', 'price_at_8'] as $field) {
            $rules[$field] = ['nullable', 'numeric', 'gt:0', 'decimal:0,6'];
        }
        foreach (['bid_open', 'bid_high', 'bid_low', 'bid_close', 'ask_open', 'ask_high', 'ask_low', 'ask_close'] as $field) {
            $rules[$field] = ['nullable', 'numeric', 'gt:0', 'decimal:0,6'];
        }

        return $rules;
    }
}
