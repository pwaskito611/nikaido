<?php

declare(strict_types=1);

namespace App\Feature\CashFlows\Model;

use App\Feature\MarketPairs\Model\MarketPair;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashFlowImportSession extends Model
{
    protected $table = 'market_pair_cash_flow_import_sessions';

    protected $fillable = [
        'market_pair_id',
        'active_market_pair_id',
        'token_hash',
        'status',
        'started_at',
        'stopped_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'stopped_at' => 'datetime',
        ];
    }

    public function marketPair(): BelongsTo
    {
        return $this->belongsTo(MarketPair::class, 'market_pair_id');
    }
}
