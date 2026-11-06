<?php

declare(strict_types=1);

namespace Nikaido\Strategy\Models;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;

final readonly class NewsItemData
{
    public function __construct(
        public int $id,
        public string $source,
        public string $title,
        public string $content,
        public DateTimeImmutable $publishedAt,
        public DateTimeImmutable $availableAt,
        public int $marketPairId,
        public float $relevanceScore,
        public float $impactScore,
        public int $impactLagSeconds,
        public int $impactDurationSeconds,
        public float $confidenceScore,
        public string $assessmentMethod,
    ) {}

    public function isImpactActiveAt(DateTimeInterface $knownAt): bool
    {
        $impactStartsAt = $this->impactStartsAt();
        $impactEndsAt = $this->impactEndsAt();

        return $this->availableAt <= $knownAt
            && $impactStartsAt <= $knownAt
            && $knownAt <= $impactEndsAt;
    }

    public function impactStartsAt(): DateTimeImmutable
    {
        return $this->publishedAt->add(new DateInterval('PT'.$this->impactLagSeconds.'S'));
    }

    public function impactEndsAt(): DateTimeImmutable
    {
        return $this->impactStartsAt()->add(new DateInterval('PT'.$this->impactDurationSeconds.'S'));
    }
}
