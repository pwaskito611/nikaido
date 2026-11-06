<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('market_pair', function (Blueprint $table): void {
            $table->decimal('tick_size', 20, 10)->nullable()->after('contract_size');
            $table->decimal('tick_value', 20, 10)->nullable()->after('tick_size');
            $table->decimal('minimum_quantity', 20, 8)->nullable()->after('tick_value');
            $table->decimal('quantity_step', 20, 8)->nullable()->after('minimum_quantity');
            $table->decimal('initial_margin_rate', 8, 4)->nullable()->after('quantity_step');
        });

        DB::table('market_pair_cash_flows')
            ->where('event_type', 'dividend')
            ->update(['event_type' => 'dividend_adjustment']);
    }

    public function down(): void
    {
        DB::table('market_pair_cash_flows')
            ->where('event_type', 'dividend_adjustment')
            ->update(['event_type' => 'dividend']);

        Schema::table('market_pair', function (Blueprint $table): void {
            $table->dropColumn([
                'tick_size',
                'tick_value',
                'minimum_quantity',
                'quantity_step',
                'initial_margin_rate',
            ]);
        });
    }
};
