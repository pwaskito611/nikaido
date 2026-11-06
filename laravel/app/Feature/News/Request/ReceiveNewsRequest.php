<?php

declare(strict_types=1);

namespace App\Feature\News\Request;

use App\Feature\News\Action\FindActiveNewsImportSessionAction;
use App\Feature\News\Model\NewsImportSession;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ReceiveNewsRequest extends FormRequest
{
    private ?NewsImportSession $activeSession = null;

    public function authorize(FindActiveNewsImportSessionAction $findSession): bool
    {
        $this->activeSession = $findSession->execute($this->bearerToken());

        return $this->activeSession !== null;
    }

    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(
            response()->json(['message' => 'No active news import session matches this bearer token.'], 401),
        );
    }

    public function activeSession(): NewsImportSession
    {
        if ($this->activeSession === null) {
            throw new AuthorizationException('An active news import session is required.');
        }

        return $this->activeSession;
    }

    protected function prepareForValidation(): void
    {
        $articles = $this->input('articles');
        if (! is_array($articles)) {
            return;
        }

        foreach ($articles as $index => $article) {
            if (! is_array($article)) {
                continue;
            }
            foreach (['published_at', 'available_at'] as $field) {
                $value = $article[$field] ?? null;
                if (is_string($value)) {
                    $value = str_replace('T', ' ', trim($value));
                    if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $value) === 1) {
                        $value .= ':00';
                    }
                    $articles[$index][$field] = $value;
                }
            }
            $articles[$index]['external_id'] ??= null;
            $articles[$index]['language'] ??= null;
            $articles[$index]['relevance_score'] ??= 1;
            $articles[$index]['impact_lag_seconds'] ??= 0;
            $articles[$index]['confidence_score'] ??= 1;
            $articles[$index]['assessment_method'] ??= 'provider';
            $articles[$index]['source'] = is_string($article['source'] ?? null)
                ? trim($article['source'])
                : $article['source'] ?? null;
            if (is_string($articles[$index]['external_id'])) {
                $articles[$index]['external_id'] = trim($articles[$index]['external_id']) ?: null;
            }
        }

        $this->merge(['articles' => $articles]);
    }

    public function rules(): array
    {
        return [
            'articles' => ['required', 'array', 'min:1', 'max:1000'],
            'articles.*.source' => ['required', 'string', 'max:100'],
            'articles.*.external_id' => ['nullable', 'string', 'max:191'],
            'articles.*.title' => ['required', 'string', 'max:500'],
            'articles.*.content' => ['required', 'string', 'max:1000000'],
            'articles.*.published_at' => ['required', 'date_format:Y-m-d H:i:s'],
            'articles.*.available_at' => ['required', 'date_format:Y-m-d H:i:s'],
            'articles.*.language' => ['nullable', 'string', 'max:10'],
            'articles.*.relevance_score' => ['required', 'numeric', 'decimal:0,5', 'between:0,1'],
            'articles.*.impact_score' => ['required', 'numeric', 'decimal:0,5', 'between:-1,1'],
            'articles.*.impact_lag_seconds' => ['required', 'integer', 'min:0', 'max:31536000'],
            'articles.*.impact_duration_seconds' => ['required', 'integer', 'gt:0', 'max:31536000'],
            'articles.*.confidence_score' => ['required', 'numeric', 'decimal:0,5', 'between:0,1'],
            'articles.*.assessment_method' => ['required', Rule::in(['manual', 'model', 'provider'])],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (strlen($this->getContent()) > 5_000_000) {
                $validator->errors()->add('articles', 'The request payload must not exceed 5 MB.');

                return;
            }

            $articles = $this->input('articles');
            if (! is_array($articles)) {
                return;
            }

            $seen = [];
            foreach ($articles as $index => $article) {
                if (! is_array($article)) {
                    continue;
                }
                if (is_string($article['published_at'] ?? null)
                    && is_string($article['available_at'] ?? null)
                    && $article['available_at'] < $article['published_at']) {
                    $validator->errors()->add(
                        "articles.{$index}.available_at",
                        'News cannot be available before its publication time.',
                    );
                }
                $identity = is_string($article['external_id'] ?? null) && $article['external_id'] !== ''
                    ? ($article['source'] ?? '').'|id|'.$article['external_id']
                    : ($article['source'] ?? '').'|natural|'.($article['title'] ?? '').'|'
                        .($article['published_at'] ?? '').'|'.($article['content'] ?? '');
                if (isset($seen[$identity])) {
                    $validator->errors()->add("articles.{$index}.external_id", 'The same news item is duplicated in this batch.');
                }
                $seen[$identity] = true;
            }
        });
    }
}
