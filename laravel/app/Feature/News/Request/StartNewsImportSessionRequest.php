<?php

declare(strict_types=1);

namespace App\Feature\News\Request;

use Illuminate\Foundation\Http\FormRequest;

class StartNewsImportSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['market_pair_id' => ['required', 'integer', 'exists:market_pair,id']];
    }
}
