<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('market_price', function (Blueprint $table): void {
            $table->dateTime('price_date')->change();
        });
    }

    public function down(): void
    {
        Schema::table('market_price', function (Blueprint $table): void {
            $table->date('price_date')->change();
        });
    }
};
