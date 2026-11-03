<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('market_price', function (Blueprint $table): void {
            $table->decimal('volume', 20, 8)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('market_price', function (Blueprint $table): void {
            $table->dropColumn('volume');
        });
    }
};
