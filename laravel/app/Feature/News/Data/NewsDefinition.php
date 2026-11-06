<?php

declare(strict_types=1);

namespace App\Feature\News\Data;

use DateTimeImmutable;

final readonly class NewsDefinition
{
    public function __construct(
        public int $marketPairId,
        public string $source,
        public ?string $externalId,
        public string $title,
        public string $content,
        public DateTimeImmutable $publishedAt,
        public DateTimeImmutable $availableAt,
        public ?string $language,
        public float $relevanceScore,
        public float $impactScore,
        public int $impactLagSeconds,
        public int $impactDurationSeconds,
        public float $confidenceScore,
        public string $assessmentMethod,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            marketPairId: (int) $data['market_pair_id'],
            source: $data['source'],
            externalId: ($data['external_id'] ?? null) ?: null,
            title: $data['title'],
            content: $data['content'],
            publishedAt: new DateTimeImmutable($data['published_at']),
            availableAt: new DateTimeImmutable($data['available_at']),
            language: ($data['language'] ?? null) ?: null,
            relevanceScore: (float) $data['relevance_score'],
            impactScore: (float) $data['impact_score'],
            impactLagSeconds: (int) $data['impact_lag_seconds'],
            impactDurationSeconds: (int) $data['impact_duration_seconds'],
            confidenceScore: (float) $data['confidence_score'],
            assessmentMethod: $data['assessment_method'],
        );
    }
}
