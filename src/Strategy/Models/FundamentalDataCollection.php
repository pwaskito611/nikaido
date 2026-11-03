<?php

declare(strict_types=1);

namespace Nikaido\Strategy\Models;

final readonly class FundamentalDataCollection
{
    /** @param array<int, FundamentalEntityData> $entities Keyed by fundamental ID. */
    public function __construct(
        private array $entities = [],
    ) {}

    public function get(int $fundamentalId): ?FundamentalEntityData
    {
        return $this->entities[$fundamentalId] ?? null;
    }

    public function has(int $fundamentalId): bool
    {
        return isset($this->entities[$fundamentalId]);
    }

    /** @return array<int, FundamentalEntityData> */
    public function all(): array
    {
        return $this->entities;
    }
}
