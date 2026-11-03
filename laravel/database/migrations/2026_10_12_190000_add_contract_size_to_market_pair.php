<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('market_pair', function (Blueprint $table): void {
            $table->decimal('contract_size', 20, 8)->default(1)->after('quote');
        });
    }

    public function down(): void
    {
        Schema::table('market_pair', function (Blueprint $table): void {
            $table->dropColumn('contract_size');
        });
    }
};
