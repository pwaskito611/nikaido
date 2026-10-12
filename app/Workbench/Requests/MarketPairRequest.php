<?php

declare(strict_types=1);

namespace App\Workbench\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MarketPairRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'base' => strtoupper(trim((string) $this->input('base'))),
            'quote' => strtoupper(trim((string) $this->input('quote'))),
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'market_type_id' => ['required', 'integer', 'exists:market_type,id'],
            'base' => [
                'required',
                'string',
                'max:10',
                Rule::unique('market_pair', 'base')
                    ->where(fn ($query) => $query
                        ->where('market_type_id', $this->input('market_type_id'))
                        ->where('quote', $this->input('quote')))
                    ->ignore($this->route('marketPair')),
            ],
            'quote' => ['required', 'string', 'max:10'],
        ];
    }
}
