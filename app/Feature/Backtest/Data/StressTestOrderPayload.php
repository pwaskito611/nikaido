<?php

declare(strict_types=1);

namespace App\Feature\Backtest\Data;

use Exact\Collection\Map;
use UnexpectedValueException;

final readonly class StressTestOrderPayload
{
    /**
     * @param  Map<string, StressTestOrderValue>  $fields
     */
    private function __construct(private Map $fields) {}

    /** @param array<string, mixed> $order */
    public static function fromExternal(array $order): self
    {
        foreach (array_keys($order) as $key) {
            if (! is_string($key)) {
                throw new UnexpectedValueException('Stress-test order payload keys must be strings.');
            }
        }

        return new self(Map::fromArray($order)->map(
            static fn (mixed $value): StressTestOrderValue => StressTestOrderValue::fromExternal($value),
        ));
    }

    /** @return array<string, mixed> */
    public function toExternal(): array
    {
        return $this->fields->map(
            static fn (StressTestOrderValue $value): mixed => $value->toExternal(),
        )->toArray();
    }
}
