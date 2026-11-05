<?php

declare(strict_types=1);

namespace Nikaido\Strategy\Models;

use DateTimeInterface;

final readonly class CashFlowDataCollection
{
    /** @param list<CashFlowEventData> $events */
    public function __construct(private array $events = []) {}

    /**
     * Return events whose information was known at the requested time.
     *
     * @return list<CashFlowEventData>
     */
    public function availableAt(DateTimeInterface $knownAt): array
    {
        return array_values(array_filter(
            $this->events,
            static fn (CashFlowEventData $event): bool => $event->availableAt <= $knownAt,
        ));
    }
}
