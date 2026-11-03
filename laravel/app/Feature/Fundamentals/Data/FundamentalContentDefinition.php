<?php

declare(strict_types=1);

namespace App\Feature\Fundamentals\Data;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class FundamentalContentDefinition
{
    /**
     * @param  array<array-key, mixed>|bool|float|int|string|null  $value
     */
    public function __construct(
        public string $code,
        public string $name,
        public array|bool|float|int|string|null $value,
        public DateTimeImmutable $datetime,
        public ?DateTimeImmutable $releasedAt,
    ) {
        if (preg_match('/^[A-Z][A-Z0-9_]{0,63}$/D', $code) !== 1) {
            throw new InvalidArgumentException('Fundamental codes must be uppercase identifiers.');
        }
        if ($name === '' || strlen($name) > 150) {
            throw new InvalidArgumentException('A fundamental content name must contain between 1 and 150 characters.');
        }
    }
}
