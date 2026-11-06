<?php

declare(strict_types=1);

namespace App\Feature\News\Request;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class NewsRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        foreach (['published_at', 'available_at'] as $field) {
            $value = $this->input($field);
            if (is_string($value)) {
                $value = str_replace('T', ' ', trim($value));
                if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $value) === 1) {
                    $value .= ':00';
                }
                $this->merge([$field => $value]);
            }
        }
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'market_pair_id' => ['required', 'integer', 'exists:market_pair,id'],
            'source' => ['required', 'string', 'max:100'],
            'external_id' => ['nullable', 'string', 'max:191'],
            'title' => ['required', 'string', 'max:500'],
            'content' => ['required', 'string', 'max:1000000'],
            'published_at' => ['required', 'date_format:Y-m-d H:i:s'],
            'available_at' => ['required', 'date_format:Y-m-d H:i:s'],
            'language' => ['nullable', 'string', 'max:10'],
            'relevance_score' => ['required', 'numeric', 'decimal:0,5', 'between:0,1'],
            'impact_score' => ['required', 'numeric', 'decimal:0,5', 'between:-1,1'],
            'impact_lag_seconds' => ['required', 'integer', 'min:0', 'max:31536000'],
            'impact_duration_seconds' => ['required', 'integer', 'gt:0', 'max:31536000'],
            'confidence_score' => ['required', 'numeric', 'decimal:0,5', 'between:0,1'],
            'assessment_method' => ['required', Rule::in(['manual', 'model', 'provider'])],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $source = $this->input('source');
            $externalId = $this->input('external_id');
            $title = $this->input('title');
            $publishedAt = $this->input('published_at');
            $content = $this->input('content');
            if (is_string($source) && is_string($title) && is_string($publishedAt) && is_string($content)) {
                $identity = is_string($externalId) && $externalId !== ''
                    ? $source.'|id|'.$externalId
                    : $source.'|natural|'.$title.'|'.$publishedAt.'|'.$content;
                $duplicateQuery = DB::table('news_items')
                    ->where('dedupe_key', hash('sha256', $identity));
                $newsId = $this->route('news');
                if ($newsId !== null) {
                    $duplicateQuery->where('id', '!=', $newsId);
                }
                if ($duplicateQuery->exists()) {
                    $validator->errors()->add('external_id', 'This source and article identity already exists.');
                }
            }

            if (is_string($this->input('published_at'))
                && is_string($this->input('available_at'))
                && $this->input('available_at') < $this->input('published_at')) {
                $validator->errors()->add('available_at', 'News cannot be available before its publication time.');
            }
        });
    }
}
