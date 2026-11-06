<?php

declare(strict_types=1);

namespace Nikaido\Strategy\Models;

use DateTimeInterface;

final readonly class NewsDataCollection
{
    /** @param list<NewsItemData> $items */
    public function __construct(private array $items = []) {}

    /** @return list<NewsItemData> */
    public function availableAt(DateTimeInterface $knownAt): array
    {
        return array_values(array_filter(
            $this->items,
            static fn (NewsItemData $item): bool => $item->availableAt <= $knownAt,
        ));
    }

    /** @return list<NewsItemData> */
    public function impactActiveAt(DateTimeInterface $knownAt): array
    {
        return array_values(array_filter(
            $this->availableAt($knownAt),
            static fn (NewsItemData $item): bool => $item->isImpactActiveAt($knownAt),
        ));
    }
}
