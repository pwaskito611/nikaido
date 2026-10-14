<?php

declare(strict_types=1);

namespace App\Features\Workbench\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MarketExplorationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pair_id' => ['nullable', 'required_with:timeframe_type_id,from,to', 'integer', 'exists:market_pair,id'],
            'timeframe_type_id' => ['nullable', 'required_with:pair_id,from,to', 'integer', 'exists:timeframe_type,id'],
            'from' => ['nullable', 'required_with:pair_id,timeframe_type_id,to', 'date_format:Y-m-d'],
            'to' => ['nullable', 'required_with:pair_id,timeframe_type_id,from', 'date_format:Y-m-d', 'after_or_equal:from'],
        ];
    }
}
