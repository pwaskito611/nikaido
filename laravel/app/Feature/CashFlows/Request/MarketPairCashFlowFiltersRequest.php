<?php

declare(strict_types=1);

namespace App\Feature\CashFlows\Request;

use Illuminate\Foundation\Http\FormRequest;

class MarketPairCashFlowFiltersRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'market_pair_id' => ['sometimes', 'nullable', 'integer', 'exists:market_pair,id'],
        ];
    }
}
