<?php

declare(strict_types=1);

namespace Nikaido\Strategy\Models;

use DateTimeInterface;

final readonly class FundamentalEntityData
{
    /**
     * @param  list<FundamentalObservation>  $observations
     */
    public function __construct(
        public int $fundamentalId,
        public string $name,
        public ?string $category,
        public ?string $description,
        private array $observations,
    ) {}

    public function has(string $code, DateTimeInterface $knownAt): bool
    {
        return $this->latestAvailable($code, $knownAt) !== null;
    }

    /**
     * Return observations released by the requested point in time.
     *
     * @return list<FundamentalObservation>
     */
    public function observations(string $code, DateTimeInterface $knownAt): array
    {
        $code = strtoupper($code);
        $observations = [];

        foreach ($this->observations as $observation) {
            if ($observation->code === $code && $observation->releasedAt <= $knownAt) {
                $observations[] = $observation;
            }
        }

        return $observations;
    }

    public function latestAvailable(string $code, DateTimeInterface $knownAt): ?FundamentalObservation
    {
        $code = strtoupper($code);
        $latest = null;
        foreach ($this->observations as $observation) {
            if ($observation->code !== $code || $observation->releasedAt > $knownAt) {
                continue;
            }
            if ($latest === null
                || $observation->datetime > $latest->datetime
                || ($observation->datetime == $latest->datetime && $observation->releasedAt > $latest->releasedAt)) {
                $latest = $observation;
            }
        }

        return $latest;
    }

    public function valueAt(
        string $code,
        DateTimeInterface $period,
        DateTimeInterface $knownAt,
    ): ?FundamentalObservation {
        $periodTimestamp = $period->format('Y-m-d H:i:s');
        $code = strtoupper($code);
        $match = null;

        foreach ($this->observations as $observation) {
            if ($observation->code !== $code
                || $observation->releasedAt > $knownAt
                || $observation->datetime->format('Y-m-d H:i:s') !== $periodTimestamp) {
                continue;
            }
            if ($match === null || $observation->releasedAt > $match->releasedAt) {
                $match = $observation;
            }
        }

        return $match;
    }
}
