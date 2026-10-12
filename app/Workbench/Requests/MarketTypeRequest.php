<?php

declare(strict_types=1);

namespace App\Workbench\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MarketTypeRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['name' => trim((string) $this->input('name'))]);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:50',
                Rule::unique('market_type', 'name')->ignore($this->route('marketType')),
            ],
        ];
    }
}
