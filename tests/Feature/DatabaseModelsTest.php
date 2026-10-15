<?php

namespace Tests\Feature;

use App\Feature\MarketPairs\Model\MarketPair;
use App\Feature\MarketPrice\Model\MarketPrice;
use App\Feature\MarketTypes\Model\MarketType;
use App\Feature\Timeframes\Model\TimeframeType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseModelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_market_models_persist_and_resolve_pair_relationships(): void
    {
        $type = MarketType::create(['name' => 'Forex']);
        $pair = MarketPair::create([
            'market_type_id' => $type->id,
            'base' => 'EUR',
            'quote' => 'USD',
            'contract_size' => '100000.00000000',
            'quantity_unit' => 'lot',
        ]);
        $timeframe = TimeframeType::create(['name' => '1h']);
        $price = MarketPrice::create([
            'pair_id' => $pair->id,
            'price_date' => '2026-10-10',
            'timeframe_type_id' => $timeframe->id,
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

        $this->assertTrue($pair->marketType->is($type));
        $this->assertTrue($type->marketPairs->first()->is($pair));
        $this->assertTrue($pair->prices->first()->is($price));
        $this->assertTrue($price->pair->is($pair));
        $this->assertTrue($price->timeframeType->is($timeframe));
        $this->assertTrue($timeframe->marketPrices->first()->is($price));
        $this->assertSame('2026-10-10', $price->fresh()->price_date->toDateString());
        $this->assertSame('1.234567', $price->fresh()->open);
        $this->assertSame('1.234700', $price->fresh()->bid_close);
        $this->assertSame('1.234800', $price->fresh()->ask_close);
        $this->assertSame('100000.00000000', $pair->fresh()->contract_size);
        $this->assertSame('lot', $pair->fresh()->quantity_unit);
    }
}
