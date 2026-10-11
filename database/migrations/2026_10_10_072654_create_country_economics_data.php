<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('country_economics_data')) {
            // Tabel sudah ada, skip pembuatan
            return;
        }

        Schema::create('country_economics_data', function (Blueprint $table) {
            $table->increments('id');
            $table->string('country_code', 10);
            $table->date('date');
            $table->double('interest_rate')->nullable();
            $table->double('supply_m0')->nullable();
            $table->double('supply_m1')->nullable();
            $table->double('supply_m2')->nullable();
            $table->double('supply_m3')->nullable();
            $table->double('mom_inflation')->nullable();
            $table->double('gdp')->nullable();
            $table->double('gdp_growth_rate')->nullable();
            $table->double('balance_of_trade')->nullable();
            $table->double('current_account')->nullable();
            $table->double('current_account_to_gdp')->nullable();
            $table->double('capital_flows')->nullable();
            $table->double('government_budget')->nullable();
            $table->double('government_debt_to_gdp')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('country_economics_data');
    }
};