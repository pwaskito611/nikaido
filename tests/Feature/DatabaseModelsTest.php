<?php

namespace Tests\Feature;

use App\Engine\Models\CountryEconomicsData;
use App\Engine\Models\EconomicsEvent;
use App\Engine\Models\MarketPair;
use App\Engine\Models\MarketPrice;
use App\Engine\Models\MarketType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseModelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_economics_models_persist_and_cast_migration_attributes(): void
    {
        $data = CountryEconomicsData::create([
            'country_code' => 'US',
            'date' => '2026-10-10',
            'interest_rate' => '4.25',
            'gdp' => '29167.8',
        ]);
        $event = EconomicsEvent::create([
            'country_code' => 'US',
            'point' => '4.5',
            'description' => 'Rate announcement',
        ]);

        $this->assertSame('2026-10-10', $data->fresh()->date->toDateString());
        $this->assertSame(4.25, $data->fresh()->interest_rate);
        $this->assertSame(29167.8, $data->fresh()->gdp);
        $this->assertSame(4.5, $event->fresh()->point);
    }

    public function test_market_models_persist_and_resolve_pair_relationships(): void
    {
        $type = MarketType::create(['name' => 'Forex']);
        $pair = MarketPair::create([
            'market_type_id' => $type->id,
            'base' => 'EUR',
            'quote' => 'USD',
        ]);
        $price = MarketPrice::create([
            'pair_id' => $pair->id,
            'price_date' => '2026-10-10',
            'timeframe_type_id' => 1,
            'open' => '1.234567',
            'close' => '1.234890',
        ]);

        $this->assertTrue($pair->marketType->is($type));
        $this->assertTrue($type->marketPairs->first()->is($pair));
        $this->assertTrue($pair->prices->first()->is($price));
        $this->assertTrue($price->pair->is($pair));
        $this->assertSame('2026-10-10', $price->fresh()->price_date->toDateString());
        $this->assertSame('1.234567', $price->fresh()->open);
    }
}
