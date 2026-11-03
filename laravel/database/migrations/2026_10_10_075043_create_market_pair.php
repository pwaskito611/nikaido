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
        if (Schema::hasTable('market_pair')) {
            return;
        }

        Schema::create('market_pair', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('market_type_id');
            $table->string('base', 10);
            $table->string('quote', 10);

            $table->index('market_type_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('market_pair');
    }
};