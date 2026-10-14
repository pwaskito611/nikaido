<?php

declare(strict_types=1);

namespace App\Features\Engine\Domain;

use DateTimeImmutable;
use DateTimeZone;
use Exact\Type\Option\Option;
use Exception;
use UnexpectedValueException;

final readonly class StressTestTimestamp
{
    private function __construct(
        public DateTimeImmutable $value,
        private string $persistedValue,
    ) {}

    /** @return Option<self> */
    public static function fromPersistedValue(mixed $value, string $field): Option
    {
        if ($value === null) {
            return Option::none();
        }
        if (! is_string($value) || trim($value) === '') {
            throw new UnexpectedValueException("Stress-test order {$field} must be a valid timestamp.");
        }
        try {
            $date = new DateTimeImmutable($value, new DateTimeZone('UTC'));
        } catch (Exception $exception) {
            throw new UnexpectedValueException(
                "Stress-test order {$field} must be a valid timestamp.",
                previous: $exception,
            );
        }
        $parseErrors = DateTimeImmutable::getLastErrors();
        if ($parseErrors !== false && ($parseErrors['warning_count'] > 0 || $parseErrors['error_count'] > 0)) {
            throw new UnexpectedValueException("Stress-test order {$field} must be a valid timestamp.");
        }

        return Option::some(new self($date, $value));
    }

    public function toPersistedValue(): string
    {
        return $this->persistedValue;
    }
}
