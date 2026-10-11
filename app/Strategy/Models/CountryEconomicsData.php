<?php

namespace App\Engine\Models;

use Illuminate\Database\Eloquent\Model;

class CountryEconomicsData extends Model
{
    protected $table = 'country_economics_data';

    public $timestamps = false;

    protected $fillable = [
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
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'interest_rate' => 'float',
            'supply_m0' => 'float',
            'supply_m1' => 'float',
            'supply_m2' => 'float',
            'supply_m3' => 'float',
            'mom_inflation' => 'float',
            'gdp' => 'float',
            'gdp_growth_rate' => 'float',
            'balance_of_trade' => 'float',
            'current_account' => 'float',
            'current_account_to_gdp' => 'float',
            'capital_flows' => 'float',
            'government_budget' => 'float',
            'government_debt_to_gdp' => 'float',
        ];
    }
}
