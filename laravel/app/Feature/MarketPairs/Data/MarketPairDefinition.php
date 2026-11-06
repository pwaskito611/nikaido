<?php

declare(strict_types=1);

namespace App\Feature\MarketPairs\Data;

use Exact\Type\Option\Option;
use InvalidArgumentException;

final readonly class MarketPairDefinition
{
    /**
     * @param  Option<string>  $contractSize
     * @param  array<int, int|null>|null  $relatedMarketPairs
     */
    private function __construct(
        public int $marketTypeId,
        public string $base,
        public string $quote,
        public MarketPairQuantityUnit $quantityUnit,
        public string $contractSize,
        public string $tickSize,
        public string $tickValue,
        public string $minimumQuantity,
        public string $quantityStep,
        public string $initialMarginRate,
        public ?array $relatedMarketPairs,
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
        string $tickSize,
        string $tickValue,
        string $minimumQuantity,
        string $quantityStep,
        string $initialMarginRate,
        ?array $relatedMarketPairs = null,
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
        if ($relatedMarketPairs !== null) {
            foreach ($relatedMarketPairs as $relatedMarketPairId => $timeframeTypeId) {
                if (! is_int($relatedMarketPairId) || $relatedMarketPairId < 1) {
                    throw new InvalidArgumentException('Related market pair IDs must be positive integers.');
                }
                if ($timeframeTypeId !== null && (! is_int($timeframeTypeId) || $timeframeTypeId < 1)) {
                    throw new InvalidArgumentException('Related market pair timeframe IDs must be positive integers or null.');
                }
            }
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
        new CfdContractSpecification(
            contractSize: self::positiveDecimal($validatedContractSize->getOrElse('1'), 'contract size'),
            tickSize: self::positiveDecimal($tickSize, 'tick size'),
            tickValue: self::positiveDecimal($tickValue, 'tick value'),
            minimumQuantity: self::positiveDecimal($minimumQuantity, 'minimum quantity'),
            quantityStep: self::positiveDecimal($quantityStep, 'quantity step'),
            initialMarginRate: self::positiveDecimal($initialMarginRate, 'initial margin rate'),
        );

        return new self(
            marketTypeId: $marketTypeId,
            base: $base,
            quote: $quote,
            quantityUnit: $quantityUnit,
            contractSize: $quantityUnit === MarketPairQuantityUnit::Units
                ? '1'
                : $validatedContractSize->getOrElseThrow(
                    static fn (): InvalidArgumentException => new InvalidArgumentException(
                        'Contract and lot quantities require a contract size.',
                    ),
                ),
            tickSize: $tickSize,
            tickValue: $tickValue,
            minimumQuantity: $minimumQuantity,
            quantityStep: $quantityStep,
            initialMarginRate: $initialMarginRate,
            relatedMarketPairs: $relatedMarketPairs,
        );
    }

    private static function positiveDecimal(string $value, string $label): float
    {
        if (! is_numeric($value) || ! is_finite((float) $value) || (float) $value <= 0) {
            throw new InvalidArgumentException("The CFD {$label} must be a positive finite number.");
        }

        return (float) $value;
    }
}
