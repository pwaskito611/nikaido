<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fundamental_import_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('fundamental_id')
                ->constrained('fundamentals')
                ->cascadeOnDelete();
            $table->unsignedBigInteger('active_fundamental_id')->nullable()->unique();
            $table->string('token_hash', 64)->unique();
            $table->string('status', 16)->default('active');
            $table->dateTime('started_at');
            $table->dateTime('stopped_at')->nullable();
            $table->timestamps();
            $table->index(['fundamental_id', 'status'], 'fundamental_import_sessions_selection_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fundamental_import_sessions');
    }
};
