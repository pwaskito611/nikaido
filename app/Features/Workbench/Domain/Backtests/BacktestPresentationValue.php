<?php

declare(strict_types=1);

namespace App\Features\Workbench\Domain\Backtests;

use DateTimeInterface;
use Exact\Collection\Map;
use Exact\Collection\Vec;
use Exact\Pattern\PatternMatch;
use Exact\Pattern\Variant;
use JsonSerializable;
use UnexpectedValueException;

final readonly class BacktestPresentationValue
{
    private function __construct(private Variant $value) {}

    public static function fromExternal(mixed $value): self
    {
        if ($value instanceof DateTimeInterface) {
            return self::fromExternal($value->format('Y-m-d H:i:s'));
        }
        if ($value instanceof JsonSerializable) {
            return self::fromExternal($value->jsonSerialize());
        }
        if (is_array($value)) {
            foreach (array_keys($value) as $key) {
                if (! is_int($key) && ! is_string($key)) {
                    throw new UnexpectedValueException('Backtest presentation records must use scalar keys.');
                }
            }

            if (array_is_list($value)) {
                return new self(Variant::of(
                    'list',
                    Vec::fromArray($value)->map(
                        static fn (mixed $item): self => self::fromExternal($item),
                    ),
                ));
            }

            return new self(Variant::of(
                'record',
                Map::fromArray($value)->map(
                    static fn (mixed $item): self => self::fromExternal($item),
                ),
            ));
        }
        if (is_object($value)) {
            return self::fromExternal(get_object_vars($value));
        }
        if (is_float($value) && ! is_finite($value)) {
            throw new UnexpectedValueException('Backtest presentation values must be finite.');
        }
        if (is_string($value) || is_int($value) || is_float($value) || is_bool($value) || $value === null) {
            return new self(Variant::of('scalar', $value));
        }

        throw new UnexpectedValueException('Backtest presentation values must be serializable.');
    }

    public function toExternal(): mixed
    {
        return PatternMatch::on($this->value, exhaustive: ['scalar', 'list', 'record'])
            ->case('scalar', static fn (Variant $value): mixed => $value->value())
            ->case('list', static function (Variant $value): array {
                $items = $value->value();
                if (! $items instanceof Vec) {
                    throw new UnexpectedValueException('A backtest presentation list must contain a Vec.');
                }

                return $items->map(
                    static fn (self $item): mixed => $item->toExternal(),
                )->toArray();
            })
            ->case('record', static function (Variant $value): array {
                $items = $value->value();
                if (! $items instanceof Map) {
                    throw new UnexpectedValueException('A backtest presentation record must contain a Map.');
                }

                return $items->map(
                    static fn (self $item): mixed => $item->toExternal(),
                )->toArray();
            })
            ->run();
    }
}
