<?php

declare(strict_types=1);

namespace App\Feature\CashFlows\Request;

use Illuminate\Foundation\Http\FormRequest;

class CashFlowImportSessionRequest extends FormRequest
{
    public function rules(): array
    {
        return ['market_pair_id' => ['required', 'integer', 'exists:market_pair,id']];
    }
}
