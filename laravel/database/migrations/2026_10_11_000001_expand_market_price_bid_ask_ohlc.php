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
        $unmigratableRows = DB::table('market_price')
            ->where(function ($query): void {
                $query->where(function ($query): void {
                    $query->whereNull('bid')
                        ->where(function ($query): void {
                            $query->whereNull('open')
                                ->orWhereNull('high')
                                ->orWhereNull('low')
                                ->orWhereNull('close');
                        });
                })->orWhere(function ($query): void {
                    $query->whereNull('ask')
                        ->where(function ($query): void {
                            $query->whereNull('open')
                                ->orWhereNull('high')
                                ->orWhereNull('low')
                                ->orWhereNull('close');
                        });
                });
            })
            ->count();

        if ($unmigratableRows > 0) {
            throw new RuntimeException(
                "Cannot migrate {$unmigratableRows} market_price row(s): bid/ask and fallback OHLC values contain nulls. Backfill those rows first.",
            );
        }

        Schema::table('market_price', function (Blueprint $table): void {
            $table->decimal('bid_open', 12, 6)->nullable();
            $table->decimal('bid_high', 12, 6)->nullable();
            $table->decimal('bid_low', 12, 6)->nullable();
            $table->decimal('bid_close', 12, 6)->nullable();
            $table->decimal('ask_open', 12, 6)->nullable();
            $table->decimal('ask_high', 12, 6)->nullable();
            $table->decimal('ask_low', 12, 6)->nullable();
            $table->decimal('ask_close', 12, 6)->nullable();
        });

        DB::table('market_price')->update([
            'bid_open' => DB::raw('COALESCE(bid + (open - close), bid, open)'),
            'bid_high' => DB::raw('COALESCE(bid + (high - close), bid, high)'),
            'bid_low' => DB::raw('COALESCE(bid + (low - close), bid, low)'),
            'bid_close' => DB::raw('COALESCE(bid, close)'),
            'ask_open' => DB::raw('COALESCE(ask + (open - close), ask, open)'),
            'ask_high' => DB::raw('COALESCE(ask + (high - close), ask, high)'),
            'ask_low' => DB::raw('COALESCE(ask + (low - close), ask, low)'),
            'ask_close' => DB::raw('COALESCE(ask, close)'),
        ]);

        Schema::table('market_price', function (Blueprint $table): void {
            $table->decimal('bid_open', 12, 6)->nullable(false)->change();
            $table->decimal('bid_high', 12, 6)->nullable(false)->change();
            $table->decimal('bid_low', 12, 6)->nullable(false)->change();
            $table->decimal('bid_close', 12, 6)->nullable(false)->change();
            $table->decimal('ask_open', 12, 6)->nullable(false)->change();
            $table->decimal('ask_high', 12, 6)->nullable(false)->change();
            $table->decimal('ask_low', 12, 6)->nullable(false)->change();
            $table->decimal('ask_close', 12, 6)->nullable(false)->change();
            $table->dropColumn(['bid', 'ask']);
        });
    }

    public function down(): void
    {
        Schema::table('market_price', function (Blueprint $table): void {
            $table->decimal('bid', 12, 6)->nullable();
            $table->decimal('ask', 12, 6)->nullable();
        });

        DB::table('market_price')->update([
            'bid' => DB::raw('bid_close'),
            'ask' => DB::raw('ask_close'),
        ]);

        Schema::table('market_price', function (Blueprint $table): void {
            $table->dropColumn([
                'bid_open',
                'bid_high',
                'bid_low',
                'bid_close',
                'ask_open',
                'ask_high',
                'ask_low',
                'ask_close',
            ]);
        });
    }
};
