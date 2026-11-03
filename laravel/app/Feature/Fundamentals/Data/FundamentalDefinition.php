<?php

declare(strict_types=1);

namespace App\Feature\Fundamentals\Data;

use InvalidArgumentException;

final readonly class FundamentalDefinition
{
    /**
     * @param  list<int>|null  $pairIds
     */
    public function __construct(
        public string $name,
        public ?string $description,
        public ?string $category,
        public ?array $pairIds,
    ) {
        if ($name === '' || strlen($name) > 150) {
            throw new InvalidArgumentException('A fundamental name must contain between 1 and 150 characters.');
        }

        if ($pairIds !== null) {
            foreach ($pairIds as $pairId) {
                if (! is_int($pairId) || $pairId <= 0) {
                    throw new InvalidArgumentException('Related market pair IDs must be positive integers.');
                }
            }
            if (count($pairIds) !== count(array_unique($pairIds))) {
                throw new InvalidArgumentException('A market pair cannot be related more than once.');
            }
        }
    }
}
