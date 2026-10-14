<?php

declare(strict_types=1);

namespace App\Features\Workbench\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MarketPriceFiltersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pair_id' => ['nullable', 'integer', 'exists:market_pair,id'],
            'timeframe_type_id' => ['nullable', 'integer', 'exists:timeframe_type,id'],
        ];
    }
}
