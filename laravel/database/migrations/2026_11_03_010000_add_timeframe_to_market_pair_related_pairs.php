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
        if (! Schema::hasColumn('market_pair_related_pairs', 'timeframe_type_id')) {
            Schema::table('market_pair_related_pairs', function (Blueprint $table): void {
                $table->foreignId('timeframe_type_id')
                    ->nullable()
                    ->after('related_market_pair_id')
                    ->constrained('timeframe_type')
                    ->cascadeOnDelete();
                $table->index('timeframe_type_id', 'market_pair_related_timeframe_index');
            });
        }

        $this->allowExplicitTimeframeForSamePair();
    }

    public function down(): void
    {
        if (! Schema::hasColumn('market_pair_related_pairs', 'timeframe_type_id')) {
            return;
        }

        $driver = DB::connection()->getDriverName();
        if ($driver === 'sqlite') {
            DB::unprepared('DROP TRIGGER IF EXISTS market_pair_related_no_self_insert');
            DB::unprepared('DROP TRIGGER IF EXISTS market_pair_related_no_self_update');
            DB::unprepared(<<<'SQL'
                CREATE TRIGGER market_pair_related_no_self_insert
                BEFORE INSERT ON market_pair_related_pairs
                FOR EACH ROW
                WHEN NEW.market_pair_id = NEW.related_market_pair_id
                BEGIN
                    SELECT RAISE(ABORT, 'A market pair cannot be related to itself');
                END
                SQL);
            DB::unprepared(<<<'SQL'
                CREATE TRIGGER market_pair_related_no_self_update
                BEFORE UPDATE OF market_pair_id, related_market_pair_id ON market_pair_related_pairs
                FOR EACH ROW
                WHEN NEW.market_pair_id = NEW.related_market_pair_id
                BEGIN
                    SELECT RAISE(ABORT, 'A market pair cannot be related to itself');
                END
                SQL);
        } else {
            $dropCheck = $driver === 'mysql' ? 'DROP CHECK' : 'DROP CONSTRAINT';
            DB::statement("ALTER TABLE market_pair_related_pairs {$dropCheck} market_pair_related_not_self");
            DB::statement(
                'ALTER TABLE market_pair_related_pairs ADD CONSTRAINT market_pair_related_not_self CHECK (market_pair_id <> related_market_pair_id)',
            );
        }

        Schema::table('market_pair_related_pairs', function (Blueprint $table): void {
            $table->dropForeign(['timeframe_type_id']);
            $table->dropIndex('market_pair_related_timeframe_index');
            $table->dropColumn('timeframe_type_id');
        });
    }

    private function allowExplicitTimeframeForSamePair(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::unprepared('DROP TRIGGER IF EXISTS market_pair_related_no_self_insert');
            DB::unprepared('DROP TRIGGER IF EXISTS market_pair_related_no_self_update');
            DB::unprepared(<<<'SQL'
                CREATE TRIGGER market_pair_related_no_self_insert
                BEFORE INSERT ON market_pair_related_pairs
                FOR EACH ROW
                WHEN NEW.market_pair_id = NEW.related_market_pair_id
                    AND NEW.timeframe_type_id IS NULL
                BEGIN
                    SELECT RAISE(ABORT, 'A market pair requires an explicit timeframe when related to itself');
                END
                SQL);
            DB::unprepared(<<<'SQL'
                CREATE TRIGGER market_pair_related_no_self_update
                BEFORE UPDATE OF market_pair_id, related_market_pair_id, timeframe_type_id ON market_pair_related_pairs
                FOR EACH ROW
                WHEN NEW.market_pair_id = NEW.related_market_pair_id
                    AND NEW.timeframe_type_id IS NULL
                BEGIN
                    SELECT RAISE(ABORT, 'A market pair requires an explicit timeframe when related to itself');
                END
                SQL);

            return;
        }

        $driver = DB::connection()->getDriverName();
        $dropCheck = $driver === 'mysql' ? 'DROP CHECK' : 'DROP CONSTRAINT';
        DB::statement("ALTER TABLE market_pair_related_pairs {$dropCheck} market_pair_related_not_self");
        DB::statement(
            'ALTER TABLE market_pair_related_pairs ADD CONSTRAINT market_pair_related_not_self CHECK (market_pair_id <> related_market_pair_id OR timeframe_type_id IS NOT NULL)',
        );
    }
};
