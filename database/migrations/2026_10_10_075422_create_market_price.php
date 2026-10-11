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
        if (Schema::hasTable('market_price')) {
            return;
        }

        Schema::create('market_price', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('pair_id');
            $table->dateTime('price_date');
            $table->unsignedBigInteger('timeframe_type_id');

            $table->decimal('open', 12, 6)->nullable();
            $table->decimal('close', 12, 6)->nullable();
            $table->decimal('bid', 12, 6)->nullable();
            $table->decimal('ask', 12, 6)->nullable();
            $table->decimal('high', 12, 6)->nullable();
            $table->decimal('low', 12, 6)->nullable();
            $table->decimal('price_at_8', 12, 6)->nullable();

            $table->index('pair_id');
            $table->index('timeframe_type_id');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('market_price');
    }
};
