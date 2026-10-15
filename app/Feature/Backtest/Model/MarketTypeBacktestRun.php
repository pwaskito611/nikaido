<?php

declare(strict_types=1);

namespace App\Feature\Backtest\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketTypeBacktestRun extends Model
{
    protected $table = 'market_type_backtest_run';

    protected $fillable = [
        'market_type_id',
        'market_type_name',
        'configuration',
        'total_pairs',
        'status',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'configuration' => 'array',
            'total_pairs' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function pairRuns(): HasMany
    {
        return $this->hasMany(MarketTypeBacktestPairRun::class, 'run_id');
    }
}
