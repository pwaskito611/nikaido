<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const QUOTE_COLUMNS = [
        'bid_open',
        'bid_high',
        'bid_low',
        'bid_close',
        'ask_open',
        'ask_high',
        'ask_low',
        'ask_close',
    ];

    public function up(): void
    {
        Schema::table('market_price', function (Blueprint $table): void {
            foreach (self::QUOTE_COLUMNS as $column) {
                $table->decimal($column, 12, 6)->nullable()->change();
            }
        });
    }

    public function down(): void
    {
        $hasNullQuotes = DB::table('market_price')
            ->where(function ($query): void {
                foreach (self::QUOTE_COLUMNS as $column) {
                    $query->orWhereNull($column);
                }
            })
            ->exists();

        if ($hasNullQuotes) {
            throw new RuntimeException(
                'Cannot make market_price bid/ask columns required while rows still contain null quotes. Backfill or remove those rows first.',
            );
        }

        Schema::table('market_price', function (Blueprint $table): void {
            foreach (self::QUOTE_COLUMNS as $column) {
                $table->decimal($column, 12, 6)->nullable(false)->change();
            }
        });
    }
};
