<?php

namespace App\Strategy\Config;

use DateTimeImmutable;
use InvalidArgumentException;

class SpecificPairTestConfig
{
    /**
     * @param  list<int>  $excludedDays  ISO-8601 weekdays, where Monday is 1 and Sunday is 7.
     */
    public function __construct(
        public readonly string $base,
        public readonly string $quote,
        public readonly DateTimeImmutable $from,
        public readonly DateTimeImmutable $to,
        public readonly string|false $activeFrom,
        public readonly string|false $activeUntil,
        public readonly array $excludedDays = [],
    ) {
        if (trim($base) === '' || trim($quote) === '') {
            throw new InvalidArgumentException('Base and quote currencies must not be empty.');
        }

        if ($from > $to) {
            throw new InvalidArgumentException('The test start date must not be after the end date.');
        }

        if (($activeFrom === false) !== ($activeUntil === false)) {
            throw new InvalidArgumentException('Both active time boundaries must be false for a full-day schedule.');
        }

        foreach ([$activeFrom, $activeUntil] as $time) {
            if ($time !== false && ! self::isValidTime($time)) {
                throw new InvalidArgumentException('Active times must use the HH:MM format.');
            }
        }

        foreach ($excludedDays as $day) {
            if (! is_int($day) || $day < 1 || $day > 7) {
                throw new InvalidArgumentException('Excluded days must be ISO-8601 weekday numbers from 1 to 7.');
            }
        }
    }

    private static function isValidTime(string $time): bool
    {
        if (preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time) !== 1) {
            return false;
        }

        return true;
    }
}
