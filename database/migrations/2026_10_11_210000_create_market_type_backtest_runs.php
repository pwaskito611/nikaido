<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('market_type_backtest_run', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('market_type_id');
            $table->string('market_type_name', 50);
            $table->json('configuration');
            $table->unsignedInteger('total_pairs')->default(0);
            $table->string('status', 32)->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('market_type_backtest_pair_run', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('run_id')->index();
            $table->unsignedBigInteger('pair_id');
            $table->string('pair_name', 32);
            $table->string('status', 32)->index();
            $table->json('result')->nullable();
            $table->text('failure_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['run_id', 'pair_id']);
            $table->foreign('run_id')->references('id')->on('market_type_backtest_run')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('market_type_backtest_pair_run');
        Schema::dropIfExists('market_type_backtest_run');
    }
};
