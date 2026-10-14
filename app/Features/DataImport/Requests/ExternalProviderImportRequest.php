<?php

declare(strict_types=1);

namespace App\Features\DataImport\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ExternalProviderImportRequest extends FormRequest
{
    private mixed $providerCredential;

    protected function prepareForValidation(): void
    {
        $this->providerCredential = $this->input('external_provider_credential');
        $this->replace($this->except('external_provider_credential'));
    }

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function validationData(): array
    {
        return [
            ...parent::validationData(),
            'external_provider_credential' => $this->providerCredential,
        ];
    }

    public function rules(): array
    {
        return [
            'pair_id' => ['required', 'integer', 'exists:market_pair,id'],
            'timeframe_type_id' => ['required', 'integer', 'exists:timeframe_type,id'],
            'external_provider_credential' => ['required', 'string', 'max:255'],
        ];
    }
}
