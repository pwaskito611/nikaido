<?php

declare(strict_types=1);

namespace App\Feature\Fundamentals\Request;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FundamentalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'category' => ['nullable', 'string', 'max:100'],
            'pair_ids_present' => ['sometimes', 'accepted'],
            'pair_ids' => ['sometimes', 'array'],
            'pair_ids.*' => ['required', 'integer', 'distinct', Rule::exists('market_pair', 'id')],
        ];
    }
}
