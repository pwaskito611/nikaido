<?php

declare(strict_types=1);

namespace App\Feature\Backtest\Data;

use Exact\Collection\Map;
use Exact\Collection\Vec;
use Exact\Pattern\PatternMatch;
use Exact\Pattern\Variant;
use UnexpectedValueException;

final readonly class StressTestOrderValue
{
    private function __construct(private Variant $value) {}

    public static function fromExternal(mixed $value): self
    {
        if (is_array($value)) {
            if (array_is_list($value)) {
                return new self(Variant::of(
                    'list',
                    Vec::fromArray($value)->map(
                        static fn (mixed $item): self => self::fromExternal($item),
                    ),
                ));
            }

            foreach (array_keys($value) as $key) {
                if (! is_string($key)) {
                    throw new UnexpectedValueException('Stress-test order records must use string keys.');
                }
            }

            return new self(Variant::of(
                'record',
                Map::fromArray($value)->map(
                    static fn (mixed $item): self => self::fromExternal($item),
                ),
            ));
        }
        if (is_float($value) && ! is_finite($value)) {
            throw new UnexpectedValueException('Stress-test order values must be finite.');
        }
        if (is_string($value) || is_int($value) || is_float($value) || is_bool($value) || $value === null) {
            return new self(Variant::of('scalar', $value));
        }

        throw new UnexpectedValueException('Stress-test order values must be JSON-compatible.');
    }

    public function toExternal(): mixed
    {
        return PatternMatch::on($this->value, exhaustive: ['scalar', 'list', 'record'])
            ->case('scalar', static fn (Variant $value): mixed => $value->value())
            ->case('list', static function (Variant $value): array {
                $items = $value->value();
                if (! $items instanceof Vec) {
                    throw new UnexpectedValueException('A stress-test order list must contain a Vec.');
                }

                return $items->map(
                    static fn (self $item): mixed => $item->toExternal(),
                )->toArray();
            })
            ->case('record', static function (Variant $value): array {
                $items = $value->value();
                if (! $items instanceof Map) {
                    throw new UnexpectedValueException('A stress-test order record must contain a Map.');
                }

                return $items->map(
                    static fn (self $item): mixed => $item->toExternal(),
                )->toArray();
            })
            ->run();
    }
}
