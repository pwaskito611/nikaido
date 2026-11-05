<?php

declare(strict_types=1);

namespace App\Feature\CashFlows\Model;

use App\Feature\MarketPairs\Model\MarketPair;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketPairCashFlow extends Model
{
    protected $fillable = [
        'market_pair_id',
        'event_type',
        'amount_per_unit',
        'announced_at',
        'available_at',
        'ex_date',
        'record_date',
        'payable_at',
        'status',
        'source',
        'external_id',
    ];

    protected function casts(): array
    {
        return [
            'amount_per_unit' => 'decimal:12',
            'announced_at' => 'datetime',
            'available_at' => 'datetime',
            'ex_date' => 'datetime',
            'record_date' => 'datetime',
            'payable_at' => 'datetime',
        ];
    }

    public function marketPair(): BelongsTo
    {
        return $this->belongsTo(MarketPair::class, 'market_pair_id');
    }
}
