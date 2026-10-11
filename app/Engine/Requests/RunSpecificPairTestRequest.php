<?php

declare(strict_types=1);

namespace App\Engine\Requests;

use App\Engine\Config\SpecificPairTestConfig;
use DateTimeImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class RunSpecificPairTestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'base' => ['required', 'string', 'max:10'],
            'quote' => ['required', 'string', 'max:10'],
            'from' => ['required', 'date_format:Y-m-d H:i:s'],
            'to' => ['required', 'date_format:Y-m-d H:i:s', 'after_or_equal:from'],
            'active_from' => ['present', $this->activeTimeRule()],
            'active_until' => ['present', $this->activeTimeRule()],
            'excluded_days' => ['sometimes', 'array'],
            'excluded_days.*' => ['integer', 'between:1,7'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $activeFrom = $this->input('active_from');
            $activeUntil = $this->input('active_until');

            if (($activeFrom === false) !== ($activeUntil === false)) {
                $validator->errors()->add(
                    'active_from',
                    'Both active time boundaries must be false for a full-day schedule.',
                );
            }
        });
    }

    public function toConfig(): SpecificPairTestConfig
    {
        $validated = $this->validated();

        return new SpecificPairTestConfig(
            base: strtoupper($validated['base']),
            quote: strtoupper($validated['quote']),
            from: new DateTimeImmutable($validated['from']),
            to: new DateTimeImmutable($validated['to']),
            activeFrom: $validated['active_from'],
            activeUntil: $validated['active_until'],
            excludedDays: array_map('intval', $validated['excluded_days'] ?? []),
        );
    }

    private function activeTimeRule(): \Closure
    {
        return static function (string $attribute, mixed $value, \Closure $fail): void {
            if ($value !== false && (! is_string($value) || preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $value) !== 1)) {
                $fail("The {$attribute} field must be a time in HH:MM format or false.");
            }
        };
    }
}
