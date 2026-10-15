<?php

declare(strict_types=1);

namespace App\Feature\Backtest\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketTypeBacktestPairRun extends Model
{
    protected $table = 'market_type_backtest_pair_run';

    protected $fillable = [
        'run_id',
        'pair_id',
        'pair_name',
        'status',
        'result',
        'failure_message',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'result' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(MarketTypeBacktestRun::class, 'run_id');
    }
}
