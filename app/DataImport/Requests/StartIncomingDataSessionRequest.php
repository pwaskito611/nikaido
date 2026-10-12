<?php

declare(strict_types=1);

namespace App\DataImport\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StartIncomingDataSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pair_id' => ['required', 'integer', 'exists:market_pair,id'],
            'timeframe_type_id' => ['required', 'integer', 'exists:timeframe_type,id'],
        ];
    }
}
