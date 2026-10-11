<?php

namespace Tests\Feature;

use App\Engine\Repositories\CountryEconomicsDataRepository;
use App\Engine\Repositories\EconomicsEventRepository;
use App\Engine\Repositories\MarketPairRepository;
use App\Engine\Repositories\MarketPriceRepository;
use App\Engine\Repositories\MarketTypeRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EngineRepositoriesTest extends TestCase
{
    use RefreshDatabase;

    public function test_country_economics_data_repository_performs_crud_operations(): void
    {
        $repository = app(CountryEconomicsDataRepository::class);
        $record = $repository->create([
            'country_code' => 'US',
            'date' => '2026-10-10',
            'gdp' => 29000,
        ]);

        $this->assertSame($record->id, $repository->find($record->id)?->id);
        $this->assertSame(30000.0, $repository->update($record->id, ['gdp' => 30000])->gdp);
        $this->assertTrue($repository->delete($record->id));
        $this->assertNull($repository->find($record->id));
    }

    public function test_economics_event_repository_performs_crud_operations(): void
    {
        $repository = app(EconomicsEventRepository::class);
        $record = $repository->create([
            'country_code' => 'US',
            'point' => 4.5,
            'description' => 'Rate announcement',
        ]);

        $this->assertSame($record->id, $repository->find($record->id)?->id);
        $this->assertSame('Updated event', $repository->update($record->id, ['description' => 'Updated event'])->description);
        $this->assertTrue($repository->delete($record->id));
        $this->assertNull($repository->find($record->id));
    }

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
        $record = $repository->create([
            'market_type_id' => $marketType->id,
            'base' => 'EUR',
            'quote' => 'USD',
        ]);

        $this->assertSame($record->id, $repository->find($record->id)?->id);
        $this->assertSame('GBP', $repository->update($record->id, ['base' => 'GBP'])->base);
        $this->assertTrue($repository->delete($record->id));
        $this->assertNull($repository->find($record->id));
    }

    public function test_market_price_repository_performs_crud_operations(): void
    {
        $marketType = app(MarketTypeRepository::class)->create(['name' => 'Forex']);
        $marketPair = app(MarketPairRepository::class)->create([
            'market_type_id' => $marketType->id,
            'base' => 'EUR',
            'quote' => 'USD',
        ]);
        $repository = app(MarketPriceRepository::class);
        $record = $repository->create([
            'pair_id' => $marketPair->id,
            'price_date' => '2026-10-10',
            'timeframe_type_id' => 1,
            'open' => '1.234567',
            'close' => '1.234890',
        ]);

        $this->assertSame($record->id, $repository->find($record->id)?->id);
        $this->assertSame('1.300000', $repository->update($record->id, ['close' => '1.300000'])->close);
        $this->assertTrue($repository->delete($record->id));
        $this->assertNull($repository->find($record->id));
    }
}
