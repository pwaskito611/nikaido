<?php

declare(strict_types=1);

namespace App\Features\Engine\Domain;

use DateTimeInterface;
use Exact\Pattern\PatternMatch;
use Exact\Pattern\Variant;
use Exact\Type\Tuple\Tuple;
use InvalidArgumentException;

final readonly class ActiveTradingSchedule
{
    private function __construct(
        private Variant $state,
    ) {}

    public static function fullDay(): self
    {
        return new self(Variant::unit('FullDay'));
    }

    public static function between(string $from, string $until): self
    {
        if (! self::isValidTime($from) || ! self::isValidTime($until)) {
            throw new InvalidArgumentException('Active times must use the HH:MM format.');
        }

        return new self(Variant::of('Window', Tuple::of2($from, $until)));
    }

    public static function fromLegacyInput(string|false $from, string|false $until): self
    {
        if ($from === false && $until === false) {
            return self::fullDay();
        }
        if ($from === false || $until === false) {
            throw new InvalidArgumentException('Both active time boundaries must be false for a full-day schedule.');
        }

        return self::between($from, $until);
    }

    public function includes(DateTimeInterface $timestamp): bool
    {
        return PatternMatch::on($this->state, exhaustive: ['FullDay', 'Window'])
            ->case('FullDay', static fn (Variant $schedule): bool => true)
            ->case('Window', static function (Variant $schedule) use ($timestamp): bool {
                /** @var Tuple<string, string> $window */
                $window = $schedule->value();
                [$from, $until] = [$window->first(), $window->second()];
                $time = $timestamp->format('H:i');

                return $from <= $until
                    ? $time >= $from && $time <= $until
                    : $time >= $from || $time <= $until;
            })
            ->run();
    }

    private static function isValidTime(string $time): bool
    {
        return preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time) === 1;
    }
}
