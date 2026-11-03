<?php

declare(strict_types=1);

namespace App\Feature\Fundamentals\Request;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StartFundamentalImportSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fundamental_id' => ['required', 'integer', Rule::exists('fundamentals', 'id')],
        ];
    }
}
