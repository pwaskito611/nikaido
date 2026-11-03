<?php

declare(strict_types=1);

namespace App\Feature\MarketPairs\Data;

use Exact\Type\Option\Option;
use InvalidArgumentException;

final readonly class MarketPairDefinition
{
    /**
     * @param  Option<string>  $contractSize
     */
    private function __construct(
        public int $marketTypeId,
        public string $base,
        public string $quote,
        public MarketPairQuantityUnit $quantityUnit,
        public string $contractSize,
    ) {}

    /**
     * @param  Option<string>  $contractSize
     */
    public static function create(
        int $marketTypeId,
        string $base,
        string $quote,
        MarketPairQuantityUnit $quantityUnit,
        Option $contractSize,
    ): self {
        $base = strtoupper(trim($base));
        $quote = strtoupper(trim($quote));

        if ($marketTypeId <= 0) {
            throw new InvalidArgumentException('A market pair must belong to a positive market type ID.');
        }
        if ($base === '' || strlen($base) > 10) {
            throw new InvalidArgumentException('A market pair base symbol must contain between 1 and 10 characters.');
        }
        if ($quote === '' || strlen($quote) > 10) {
            throw new InvalidArgumentException('A market pair quote symbol must contain between 1 and 10 characters.');
        }
        $validatedContractSize = $contractSize->map(static function (mixed $size): string {
            if (! is_string($size)
                || preg_match('/^\d{1,12}(?:\.\d{0,8})?$/D', $size) !== 1
                || ! is_finite((float) $size)
                || (float) $size <= 0) {
                throw new InvalidArgumentException('A market pair contract size must be a positive finite number.');
            }

            return $size;
        });

        return new self(
            $marketTypeId,
            $base,
            $quote,
            $quantityUnit,
            $quantityUnit === MarketPairQuantityUnit::Units
                ? '1'
                : $validatedContractSize->getOrElseThrow(
                    static fn (): InvalidArgumentException => new InvalidArgumentException(
                        'Contract and lot quantities require a contract size.',
                    ),
                ),
        );
    }
}
