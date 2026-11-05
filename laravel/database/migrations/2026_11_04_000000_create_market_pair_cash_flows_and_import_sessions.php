<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('market_pair_cash_flows')) {
            Schema::create('market_pair_cash_flows', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('market_pair_id')
                    ->constrained('market_pair')
                    ->cascadeOnDelete();
                $table->string('event_type', 32);
                $table->decimal('amount_per_unit', 28, 12);
                $table->dateTime('announced_at')->nullable();
                $table->dateTime('available_at');
                $table->dateTime('ex_date')->nullable();
                $table->dateTime('record_date')->nullable();
                $table->dateTime('payable_at')->nullable();
                $table->string('status', 16)->default('announced');
                $table->string('source', 150)->nullable();
                $table->string('external_id', 191)->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasIndex('market_pair_cash_flows', 'market_pair_cash_flow_external_unique')) {
            Schema::table('market_pair_cash_flows', function (Blueprint $table): void {
                $table->unique(['market_pair_id', 'external_id'], 'market_pair_cash_flow_external_unique');
            });
        }
        if (! Schema::hasIndex('market_pair_cash_flows', 'market_pair_cash_flow_available_index')) {
            Schema::table('market_pair_cash_flows', function (Blueprint $table): void {
                $table->index(['market_pair_id', 'available_at'], 'market_pair_cash_flow_available_index');
            });
        }
        if (! Schema::hasIndex('market_pair_cash_flows', 'market_pair_cash_flow_ex_date_index')) {
            Schema::table('market_pair_cash_flows', function (Blueprint $table): void {
                $table->index(['market_pair_id', 'ex_date'], 'market_pair_cash_flow_ex_date_index');
            });
        }
        if (! Schema::hasIndex('market_pair_cash_flows', 'market_pair_cash_flow_payable_index')) {
            Schema::table('market_pair_cash_flows', function (Blueprint $table): void {
                $table->index(['market_pair_id', 'payable_at'], 'market_pair_cash_flow_payable_index');
            });
        }

        if (! Schema::hasTable('market_pair_cash_flow_import_sessions')) {
            Schema::create('market_pair_cash_flow_import_sessions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('market_pair_id')
                    ->constrained('market_pair')
                    ->cascadeOnDelete();
                $table->unsignedBigInteger('active_market_pair_id')->nullable();
                $table->string('token_hash', 64);
                $table->string('status', 16)->default('active');
                $table->dateTime('started_at');
                $table->dateTime('stopped_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasIndex('market_pair_cash_flow_import_sessions', ['active_market_pair_id'], 'unique')) {
            Schema::table('market_pair_cash_flow_import_sessions', function (Blueprint $table): void {
                $table->unique(['active_market_pair_id'], 'cash_flow_sessions_active_pair_unique');
            });
        }
        if (! Schema::hasIndex('market_pair_cash_flow_import_sessions', ['token_hash'], 'unique')) {
            Schema::table('market_pair_cash_flow_import_sessions', function (Blueprint $table): void {
                $table->unique(['token_hash'], 'cash_flow_sessions_token_hash_unique');
            });
        }
        if (! Schema::hasIndex(
            'market_pair_cash_flow_import_sessions',
            'market_pair_cash_flow_sessions_selection_index',
        )) {
            Schema::table('market_pair_cash_flow_import_sessions', function (Blueprint $table): void {
                $table->index(['market_pair_id', 'status'], 'market_pair_cash_flow_sessions_selection_index');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('market_pair_cash_flow_import_sessions');
        Schema::dropIfExists('market_pair_cash_flows');
    }
};
