<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('market_pair', function (Blueprint $table): void {
            $table->string('quantity_unit', 16)->default('lot')->after('contract_size');
        });
    }

    public function down(): void
    {
        Schema::table('market_pair', function (Blueprint $table): void {
            $table->dropColumn('quantity_unit');
        });
    }
};
