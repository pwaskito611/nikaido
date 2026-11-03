<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DatabaseMigrationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_domain_migrations_create_tables_with_expected_columns(): void
    {
        $expectedColumns = [
            'market_type' => [
                'id',
                'name',
            ],
            'timeframe_type' => [
                'id',
                'name',
            ],
            'market_pair' => [
                'id',
                'market_type_id',
                'base',
                'quote',
                'contract_size',
                'quantity_unit',
            ],
            'market_price' => [
                'id',
                'pair_id',
                'price_date',
                'timeframe_type_id',
                'open',
                'close',
                'high',
                'low',
                'price_at_8',
                'bid_open',
                'bid_high',
                'bid_low',
                'bid_close',
                'ask_open',
                'ask_high',
                'ask_low',
                'ask_close',
            ],
        ];

        foreach ($expectedColumns as $table => $columns) {
            $this->assertTrue(Schema::hasTable($table), "Missing table: {$table}");
            $this->assertSame($columns, Schema::getColumnListing($table), "Unexpected columns in {$table}");
        }

        $this->assertFalse(Schema::hasTable('country_economics_data'));
        $this->assertFalse(Schema::hasTable('economics_events'));
        $this->assertTrue(Schema::hasTable('sessions'));
        $this->assertFalse(Schema::hasTable('users'));
        $this->assertFalse(Schema::hasTable('password_reset_tokens'));
    }

    public function test_domain_migrations_apply_required_unique_and_index_constraints(): void
    {
        $marketTypeIndexes = collect(Schema::getIndexes('market_type'));
        $timeframeTypeIndexes = collect(Schema::getIndexes('timeframe_type'));
        $marketPairIndexes = collect(Schema::getIndexes('market_pair'));
        $marketPriceIndexes = collect(Schema::getIndexes('market_price'));

        $this->assertTrue($marketTypeIndexes->contains(
            fn (array $index): bool => $index['unique'] && $index['columns'] === ['name']
        ));
        $this->assertTrue($timeframeTypeIndexes->contains(
            fn (array $index): bool => $index['unique'] && $index['columns'] === ['name']
        ));
        $this->assertTrue($marketPairIndexes->contains(
            fn (array $index): bool => $index['columns'] === ['market_type_id']
        ));
        $this->assertTrue($marketPriceIndexes->contains(
            fn (array $index): bool => $index['columns'] === ['pair_id']
        ));
        $this->assertTrue($marketPriceIndexes->contains(
            fn (array $index): bool => $index['columns'] === ['timeframe_type_id']
        ));
    }

    public function test_bid_ask_migration_backfills_quote_ohlc_using_legacy_spread(): void
    {
        $migration = require database_path('migrations/2026_10_11_000001_expand_market_price_bid_ask_ohlc.php');
        $migration->down();

        $id = DB::table('market_price')->insertGetId([
            'pair_id' => 1,
            'price_date' => '2026-10-10 00:00:00',
            'timeframe_type_id' => 1,
            'open' => '1.098000',
            'high' => '1.102000',
            'low' => '1.097000',
            'close' => '1.100000',
            'bid' => '1.099000',
            'ask' => '1.101000',
        ]);

        $migration->up();
        $price = DB::table('market_price')->find($id);

        $this->assertEqualsWithDelta(1.097, (float) $price->bid_open, 0.000001);
        $this->assertEqualsWithDelta(1.101, (float) $price->bid_high, 0.000001);
        $this->assertEqualsWithDelta(1.096, (float) $price->bid_low, 0.000001);
        $this->assertEqualsWithDelta(1.099, (float) $price->bid_close, 0.000001);
        $this->assertEqualsWithDelta(1.099, (float) $price->ask_open, 0.000001);
        $this->assertEqualsWithDelta(1.103, (float) $price->ask_high, 0.000001);
        $this->assertEqualsWithDelta(1.098, (float) $price->ask_low, 0.000001);
        $this->assertEqualsWithDelta(1.101, (float) $price->ask_close, 0.000001);
    }
}
