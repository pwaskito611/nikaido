<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_import_session', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('pair_id');
            $table->unsignedBigInteger('timeframe_type_id');
            $table->char('token_hash', 64)->unique();
            $table->string('status', 16)->index();
            $table->timestamp('started_at');
            $table->timestamp('stopped_at')->nullable();
            $table->timestamps();
            $table->index(['pair_id', 'timeframe_type_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_import_session');
    }
};
