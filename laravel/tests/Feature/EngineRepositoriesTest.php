<?php

namespace Tests\Feature;

use App\Feature\MarketPairs\Repository\MarketPairRepository;
use App\Feature\MarketPrice\Repository\MarketPriceRepository;
use App\Feature\MarketTypes\Repository\MarketTypeRepository;
use App\Feature\MarketPairs\Data\MarketPairDefinition;
use App\Feature\MarketPairs\Data\MarketPairQuantityUnit;
use Exact\Collection\Vec;
use Exact\Type\Option\Option;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EngineRepositoriesTest extends TestCase
{
    use RefreshDatabase;

    public function test_market_type_repository_performs_crud_operations(): void
    {
        $repository = app(MarketTypeRepository::class);
        $record = $repository->create(['name' => 'Forex']);

        $this->assertSame($record->id, $repository->find($record->id)?->id);
        $this->assertSame('Crypto', $repository->update($record->id, ['name' => 'Crypto'])->name);
        $this->assertTrue($repository->delete($record->id));
        $this->assertNull($repository->find($record->id));
    }

    public function test_market_pair_repository_performs_crud_operations(): void
    {
        $marketType = app(MarketTypeRepository::class)->create(['name' => 'Forex']);
        $repository = app(MarketPairRepository::class);
        $record = $repository->create(MarketPairDefinition::create(
            $marketType->id,
            'EUR',
            'USD',
            MarketPairQuantityUnit::Units,
            Option::none(),
        ));

        $this->assertSame($record->id, $repository->find($record->id)?->id);
        $updated = $repository->update($record->id, MarketPairDefinition::create(
            $marketType->id,
            'GBP',
            'USD',
            MarketPairQuantityUnit::Units,
            Option::none(),
        ));
        $this->assertSame('GBP', $updated->base);
        $this->assertTrue($repository->delete($record->id));
        $this->assertNull($repository->find($record->id));
    }

    public function test_market_price_repository_performs_crud_operations(): void
    {
        $marketType = app(MarketTypeRepository::class)->create(['name' => 'Forex']);
        $marketPair = app(MarketPairRepository::class)->create(MarketPairDefinition::create(
            $marketType->id,
            'EUR',
            'USD',
            MarketPairQuantityUnit::Units,
            Option::none(),
        ));
        $repository = app(MarketPriceRepository::class);
        $record = $repository->create([
            'pair_id' => $marketPair->id,
            'price_date' => '2026-10-10',
            'timeframe_type_id' => 1,
            'open' => '1.234567',
            'close' => '1.234890',
            'bid_open' => '1.234500',
            'bid_high' => '1.234800',
            'bid_low' => '1.234400',
            'bid_close' => '1.234700',
            'ask_open' => '1.234600',
            'ask_high' => '1.234900',
            'ask_low' => '1.234500',
            'ask_close' => '1.234800',
        ]);

        $this->assertSame($record->id, $repository->find($record->id)?->id);
        $candles = $repository->candlesBetween(
            $marketPair->id,
            1,
            '2026-10-10 00:00:00',
            '2026-10-10 23:59:59',
        );
        $this->assertInstanceOf(Vec::class, $candles);
        $this->assertCount(1, $candles);
        $this->assertSame('2026-10-10 00:00:00', $candles->first()->price_date->format('Y-m-d H:i:s'));
        $this->assertSame('1.300000', $repository->update($record->id, ['close' => '1.300000'])->close);
        $this->assertTrue($repository->delete($record->id));
        $this->assertNull($repository->find($record->id));
    }
}
