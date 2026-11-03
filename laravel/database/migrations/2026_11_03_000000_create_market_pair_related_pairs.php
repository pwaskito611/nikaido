<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('market_pair_related_pairs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('market_pair_id')
                ->constrained('market_pair')
                ->cascadeOnDelete();
            $table->foreignId('related_market_pair_id')
                ->constrained('market_pair')
                ->cascadeOnDelete();
            $table->foreignId('timeframe_type_id')
                ->nullable()
                ->constrained('timeframe_type')
                ->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['market_pair_id', 'related_market_pair_id'], 'market_pair_related_unique');
            $table->index('related_market_pair_id', 'market_pair_related_reverse_index');
            $table->index('timeframe_type_id', 'market_pair_related_timeframe_index');
        });

        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::unprepared(<<<'SQL'
                CREATE TRIGGER market_pair_related_no_self_insert
                BEFORE INSERT ON market_pair_related_pairs
                FOR EACH ROW
                WHEN NEW.market_pair_id = NEW.related_market_pair_id
                    AND NEW.timeframe_type_id IS NULL
                BEGIN
                    SELECT RAISE(ABORT, 'A market pair cannot be related to itself');
                END
                SQL);
            DB::unprepared(<<<'SQL'
                CREATE TRIGGER market_pair_related_no_self_update
                BEFORE UPDATE OF market_pair_id, related_market_pair_id, timeframe_type_id ON market_pair_related_pairs
                FOR EACH ROW
                WHEN NEW.market_pair_id = NEW.related_market_pair_id
                    AND NEW.timeframe_type_id IS NULL
                BEGIN
                    SELECT RAISE(ABORT, 'A market pair cannot be related to itself');
                END
                SQL);

            return;
        }

        DB::statement(
            'ALTER TABLE market_pair_related_pairs ADD CONSTRAINT market_pair_related_not_self CHECK (market_pair_id <> related_market_pair_id OR timeframe_type_id IS NOT NULL)',
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('market_pair_related_pairs');
    }
};
