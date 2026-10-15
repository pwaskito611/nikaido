<?php

declare(strict_types=1);

namespace App\Feature\Backtest\Data\Analysis;

use Exact\Collection\Map;
use UnexpectedValueException;

final readonly class BacktestPresentationData
{
    /**
     * @param  Map<string, BacktestPresentationValue>  $fields
     */
    private function __construct(private Map $fields) {}

    /** @param array<string, mixed> $data */
    public static function fromExternal(array $data): self
    {
        foreach (array_keys($data) as $key) {
            if (! is_string($key)) {
                throw new UnexpectedValueException('Backtest presentation data must use string keys.');
            }
        }

        return new self(Map::fromArray($data)->map(
            static fn (mixed $value): BacktestPresentationValue => BacktestPresentationValue::fromExternal($value),
        ));
    }

    /** @return array<string, mixed> */
    public function toExternal(): array
    {
        return $this->fields->map(
            static fn (BacktestPresentationValue $value): mixed => $value->toExternal(),
        )->toArray();
    }
}
