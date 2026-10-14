<?php

declare(strict_types=1);

namespace App\Features\Engine\Config;

use App\Features\Engine\Domain\ActiveTradingSchedule;
use DateTimeImmutable;
use Exact\Collection\Vec;
use InvalidArgumentException;

class SpecificPairTestConfig
{
    public readonly ActiveTradingSchedule $activeSchedule;

    /** @var Vec<int> */
    public readonly Vec $excludedDays;

    /**
     * PHP-native inputs are accepted here only to normalize request/test fixtures
     * into the Exact-backed values retained by this configuration.
     *
     * @param  list<int>|Vec<int>  $excludedDays  ISO-8601 weekdays, where Monday is 1 and Sunday is 7.
     */
    public function __construct(
        public readonly string $base,
        public readonly string $quote,
        public readonly DateTimeImmutable $from,
        public readonly DateTimeImmutable $to,
        string|false|ActiveTradingSchedule $activeFrom,
        string|false|null $activeUntil = null,
        array|Vec $excludedDays = [],
        public readonly ?int $timeframeTypeId = null,
        public readonly float $initialCapital = 10000.0,
        public readonly ?int $marketPairId = null,
        public readonly FixedSlippageConfig $slippage = new FixedSlippageConfig,
        public readonly PartialFillConfig $partialFill = new PartialFillConfig,
    ) {
        if ($activeFrom instanceof ActiveTradingSchedule) {
            if ($activeUntil !== null) {
                throw new InvalidArgumentException('An active schedule cannot be combined with legacy active time boundaries.');
            }
            $this->activeSchedule = $activeFrom;
        } else {
            if ($activeUntil === null) {
                throw new InvalidArgumentException('Both legacy active time boundaries are required.');
            }
            $this->activeSchedule = ActiveTradingSchedule::fromLegacyInput($activeFrom, $activeUntil);
        }
        $this->excludedDays = $excludedDays instanceof Vec
            ? $excludedDays
            : Vec::fromArray($excludedDays);

        if (trim($base) === '' || trim($quote) === '') {
            throw new InvalidArgumentException('Base and quote currencies must not be empty.');
        }

        if ($from > $to) {
            throw new InvalidArgumentException('The test start date must not be after the end date.');
        }

        foreach ($this->excludedDays as $day) {
            if (! is_int($day) || $day < 1 || $day > 7) {
                throw new InvalidArgumentException('Excluded days must be ISO-8601 weekday numbers from 1 to 7.');
            }
        }

        if ($timeframeTypeId !== null && $timeframeTypeId < 1) {
            throw new InvalidArgumentException('The timeframe type ID must be a positive integer.');
        }

        if ($marketPairId !== null && $marketPairId < 1) {
            throw new InvalidArgumentException('The market pair ID must be a positive integer.');
        }

        if (! is_finite($initialCapital) || $initialCapital <= 0) {
            throw new InvalidArgumentException('Initial capital must be a positive finite number.');
        }

    }

}
