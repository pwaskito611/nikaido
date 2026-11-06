<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('news_items', function (Blueprint $table): void {
            $table->id();
            $table->string('source', 100);
            $table->string('external_id', 191)->nullable();
            $table->char('dedupe_key', 64)->unique('news_items_dedupe_key_unique');
            $table->string('title', 500);
            $table->longText('content');
            $table->dateTime('published_at');
            $table->dateTime('available_at');
            $table->string('language', 10)->nullable();
            $table->timestamps();
            $table->index(['available_at', 'published_at'], 'news_items_availability_index');
        });

        Schema::create('news_market_pair_impacts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('news_item_id')->constrained('news_items')->cascadeOnDelete();
            $table->foreignId('market_pair_id')->constrained('market_pair')->cascadeOnDelete();
            $table->decimal('relevance_score', 6, 5)->default(1);
            $table->decimal('impact_score', 6, 5);
            $table->unsignedInteger('impact_lag_seconds')->default(0);
            $table->unsignedInteger('impact_duration_seconds');
            $table->decimal('confidence_score', 6, 5)->default(1);
            $table->string('assessment_method', 30)->default('manual');
            $table->timestamps();
            $table->unique(['news_item_id', 'market_pair_id'], 'news_pair_impact_unique');
            $table->index(['market_pair_id', 'news_item_id'], 'news_pair_impact_pair_index');
        });

        Schema::create('news_import_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('market_pair_id')->constrained('market_pair')->cascadeOnDelete();
            $table->unsignedBigInteger('active_market_pair_id')->nullable()->unique('news_import_active_pair_unique');
            $table->string('token_hash', 64)->unique('news_import_token_hash_unique');
            $table->string('status', 16)->default('active');
            $table->dateTime('started_at');
            $table->dateTime('stopped_at')->nullable();
            $table->timestamps();
            $table->index(['market_pair_id', 'status'], 'news_import_pair_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('news_import_sessions');
        Schema::dropIfExists('news_market_pair_impacts');
        Schema::dropIfExists('news_items');
    }
};
