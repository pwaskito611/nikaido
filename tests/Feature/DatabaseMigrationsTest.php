<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DatabaseMigrationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_domain_migrations_create_tables_with_expected_columns(): void
    {
        $expectedColumns = [
            'country_economics_data' => [
                'id',
                'country_code',
                'date',
                'interest_rate',
                'supply_m0',
                'supply_m1',
                'supply_m2',
                'supply_m3',
                'mom_inflation',
                'gdp',
                'gdp_growth_rate',
                'balance_of_trade',
                'current_account',
                'current_account_to_gdp',
                'capital_flows',
                'government_budget',
                'government_debt_to_gdp',
            ],
            'economics_events' => [
                'id',
                'country_code',
                'point',
                'description',
            ],
            'market_type' => [
                'id',
                'name',
            ],
            'market_pair' => [
                'id',
                'market_type_id',
                'base',
                'quote',
            ],
            'market_price' => [
                'id',
                'pair_id',
                'price_date',
                'timeframe_type_id',
                'open',
                'close',
                'bid',
                'ask',
                'high',
                'low',
                'price_at_8',
            ],
        ];

        foreach ($expectedColumns as $table => $columns) {
            $this->assertTrue(Schema::hasTable($table), "Missing table: {$table}");
            $this->assertSame($columns, Schema::getColumnListing($table), "Unexpected columns in {$table}");
        }
    }

    public function test_domain_migrations_apply_required_unique_and_index_constraints(): void
    {
        $marketTypeIndexes = collect(Schema::getIndexes('market_type'));
        $marketPairIndexes = collect(Schema::getIndexes('market_pair'));
        $marketPriceIndexes = collect(Schema::getIndexes('market_price'));

        $this->assertTrue($marketTypeIndexes->contains(
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
}
