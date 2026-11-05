<?php

declare(strict_types=1);

namespace Nikaido\Strategy\Models;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class CashFlowEventData
{
    public function __construct(
        public int $id,
        public string $eventType,
        public float $amountPerUnit,
        public DateTimeImmutable $availableAt,
        public ?DateTimeImmutable $announcedAt,
        public ?DateTimeImmutable $exDate,
        public ?DateTimeImmutable $recordDate,
        public ?DateTimeImmutable $payableAt,
        public string $status,
        public ?string $source,
    ) {
        if ($id < 1 || $eventType === '' || ! is_finite($amountPerUnit) || $amountPerUnit <= 0) {
            throw new InvalidArgumentException('Cash flow events require an ID, type, and positive finite amount.');
        }
    }
}
