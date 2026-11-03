<?php

declare(strict_types=1);

namespace Tests\Unit\Workbench\Domain\MarketPairs;

use App\Feature\MarketPairs\Data\MarketPairDefinition;
use App\Feature\MarketPairs\Data\MarketPairQuantityUnit;
use Exact\Type\Option\Option;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class MarketPairDefinitionTest extends TestCase
{
    public function test_it_normalizes_symbols_and_forces_unit_contract_size_to_one(): void
    {
        $definition = MarketPairDefinition::create(
            7,
            ' eur ',
            'usd',
            MarketPairQuantityUnit::Units,
            Option::some('500'),
        );

        $this->assertSame('EUR', $definition->base);
        $this->assertSame('USD', $definition->quote);
        $this->assertSame('1', $definition->contractSize);
    }

    public function test_contract_and_lot_units_require_a_positive_finite_size(): void
    {
        foreach ([MarketPairQuantityUnit::Contract, MarketPairQuantityUnit::Lot] as $unit) {
            try {
                MarketPairDefinition::create(7, 'ES', 'USD', $unit, Option::none());
                $this->fail('Expected missing contract size to be rejected.');
            } catch (InvalidArgumentException $exception) {
                $this->assertSame('Contract and lot quantities require a contract size.', $exception->getMessage());
            }
        }

        foreach (['0', '-1', 'NAN', 'INF', '1.123456789', '1234567890123'] as $size) {
            try {
                MarketPairDefinition::create(7, 'ES', 'USD', MarketPairQuantityUnit::Contract, Option::some($size));
                $this->fail('Expected invalid contract size to be rejected.');
            } catch (InvalidArgumentException $exception) {
                $this->assertSame('A market pair contract size must be a positive finite number.', $exception->getMessage());
            }
        }
    }

    public function test_it_rejects_invalid_market_type_ids_and_symbols(): void
    {
        foreach ([
            [0, 'EUR', 'USD'],
            [7, '', 'USD'],
            [7, '12345678901', 'USD'],
            [7, 'EUR', ''],
            [7, 'EUR', '12345678901'],
        ] as [$marketTypeId, $base, $quote]) {
            try {
                MarketPairDefinition::create(
                    $marketTypeId,
                    $base,
                    $quote,
                    MarketPairQuantityUnit::Units,
                    Option::none(),
                );
                $this->fail('Expected invalid market pair identity to be rejected.');
            } catch (InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }

    }

    public function test_it_rejects_invalid_related_pair_or_timeframe_ids(): void
    {
        foreach ([
            [0 => 1],
            [-1 => 1],
            [1 => 0],
            [1 => '2'],
        ] as $relatedPairs) {
            try {
                MarketPairDefinition::create(
                    7,
                    'EUR',
                    'USD',
                    MarketPairQuantityUnit::Units,
                    Option::none(),
                    $relatedPairs,
                );
                $this->fail('Expected invalid related market pair IDs to be rejected.');
            } catch (InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_it_accepts_related_pairs_with_individual_timeframes_or_primary_timeframe_fallback(): void
    {
        $definition = MarketPairDefinition::create(
            7,
            'EUR',
            'GBP',
            MarketPairQuantityUnit::Units,
            Option::none(),
            [12 => 4, 13 => null],
        );

        $this->assertSame([12 => 4, 13 => null], $definition->relatedMarketPairs);
    }
}
