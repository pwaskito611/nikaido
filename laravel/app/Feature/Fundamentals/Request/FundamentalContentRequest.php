<?php

declare(strict_types=1);

namespace App\Feature\Fundamentals\Request;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FundamentalContentRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $datetime = $this->normalizeDatetime($this->input('datetime'));
        $releasedAt = $this->normalizeDatetime($this->input('released_at'));

        $this->merge([
            'code' => strtoupper(trim((string) $this->input('code'))),
            'datetime' => $datetime,
            'released_at' => $releasedAt,
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:64', 'regex:/^[A-Z][A-Z0-9_]*$/D'],
            'name' => ['required', 'string', 'max:150'],
            'value' => ['required', 'string', 'json'],
            'datetime' => [
                'required',
                'date_format:Y-m-d H:i:s',
                Rule::unique('fundamental_contents', 'datetime')
                    ->where(fn ($query) => $query
                        ->where('fundamental_id', $this->route('fundamental'))
                        ->where('code', $this->input('code')))
                    ->ignore($this->route('content')),
            ],
            'released_at' => ['nullable', 'date_format:Y-m-d H:i:s'],
        ];
    }

    private function normalizeDatetime(mixed $datetime): mixed
    {
        if (! is_string($datetime)) {
            return $datetime;
        }

        $datetime = str_replace('T', ' ', trim($datetime));
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $datetime) === 1) {
            return $datetime.':00';
        }
        if ($datetime === '') {
            return null;
        }

        return $datetime;
    }
}
