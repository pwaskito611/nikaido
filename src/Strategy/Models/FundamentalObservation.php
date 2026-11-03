<?php

declare(strict_types=1);

namespace Nikaido\Strategy\Models;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class FundamentalObservation
{
    /**
     * @param  array<array-key, mixed>|bool|float|int|string|null  $value
     */
    public function __construct(
        public string $code,
        public string $name,
        public DateTimeImmutable $datetime,
        public ?DateTimeImmutable $releasedAt,
        public array|bool|float|int|string|null $value,
    ) {
        if ($code === '' || $name === '') {
            throw new InvalidArgumentException('Fundamental observations require a code and display name.');
        }

        if ($releasedAt === null) {
            throw new InvalidArgumentException('Fundamental observations must have a release time to be available to a strategy.');
        }
    }
}
