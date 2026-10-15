<?php

declare(strict_types=1);

namespace App\Feature\Backtest\Data\Analysis;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use UnexpectedValueException;

final readonly class BacktestAnalysisTimestamp
{
    private function __construct(
        public DateTimeImmutable $instant,
        private string $persistedValue,
    ) {}

    public static function fromPersistedValue(mixed $value, string $field): self
    {
        if (! is_string($value) || trim($value) === '') {
            throw new UnexpectedValueException("A backtest {$field} must be a valid date and time.");
        }

        try {
            $instant = new DateTimeImmutable($value, new DateTimeZone('UTC'));
        } catch (Exception $exception) {
            throw new UnexpectedValueException(
                "A backtest {$field} must be a valid date and time.",
                previous: $exception,
            );
        }

        $parseErrors = DateTimeImmutable::getLastErrors();
        if ($parseErrors !== false && ($parseErrors['warning_count'] > 0 || $parseErrors['error_count'] > 0)) {
            throw new UnexpectedValueException("A backtest {$field} must be a valid date and time.");
        }

        return new self($instant, $value);
    }

    public function isBefore(self $other): bool
    {
        return $this->instant < $other->instant;
    }

    public function isAfterOrEqual(self $other): bool
    {
        return $this->instant >= $other->instant;
    }

    public function toPersistedValue(): string
    {
        return $this->persistedValue;
    }
}
