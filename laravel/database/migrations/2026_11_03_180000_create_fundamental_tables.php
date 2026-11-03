<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fundamentals', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->string('category', 100)->nullable();
            $table->timestamps();
        });

        Schema::create('fundamental_contents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('fundamental_id')
                ->constrained('fundamentals')
                ->cascadeOnDelete();
            $table->string('code', 64);
            $table->string('name', 150);
            $table->json('value');
            $table->dateTime('datetime');
            $table->dateTime('released_at')->nullable();
            $table->timestamps();
            $table->unique(
                ['fundamental_id', 'code', 'datetime'],
                'fundamental_contents_observation_unique',
            );
            $table->index(['fundamental_id', 'released_at'], 'fundamental_contents_release_index');
        });

        Schema::create('fundamental_related', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('fundamental_id')
                ->constrained('fundamentals')
                ->cascadeOnDelete();
            $table->foreignId('pair_id')
                ->constrained('market_pair')
                ->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['fundamental_id', 'pair_id'], 'fundamental_related_unique');
            $table->index('pair_id', 'fundamental_related_pair_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fundamental_related');
        Schema::dropIfExists('fundamental_contents');
        Schema::dropIfExists('fundamentals');
    }
};
