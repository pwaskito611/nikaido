<?php

declare(strict_types=1);

namespace App\Features\Engine\Domain;

use Exact\Type\Option\Option;
use UnexpectedValueException;

enum StressTestPriceSource: string
{
    case Close = 'close';
    case Low = 'low';
    case High = 'high';
    case BidClose = 'bid_close';
    case AskClose = 'ask_close';
    case BidLow = 'bid_low';
    case AskHigh = 'ask_high';
    case BidHigh = 'bid_high';
    case AskLow = 'ask_low';

    /** @return Option<self> */
    public static function fromPersistedValue(mixed $value, string $field): Option
    {
        if ($value === null) {
            return Option::none();
        }
        if (! is_string($value) || self::tryFrom($value) === null) {
            throw new UnexpectedValueException("Stress-test order {$field} is unsupported.");
        }

        return Option::some(self::from($value));
    }
}
