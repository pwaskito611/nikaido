<?php

declare(strict_types=1);

namespace App\Feature\MarketPrice\Model;

use App\Feature\MarketPairs\Model\MarketPair;
use App\Feature\Timeframes\Model\TimeframeType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketPrice extends Model
{
    protected $table = 'market_price';

    public $timestamps = false;

    protected $fillable = [
        'pair_id',
        'price_date',
        'timeframe_type_id',
        'open',
        'close',
        'bid_open',
        'bid_high',
        'bid_low',
        'bid_close',
        'ask_open',
        'ask_high',
        'ask_low',
        'ask_close',
        'high',
        'low',
        'price_at_8',
    ];

    protected function casts(): array
    {
        return [
            'price_date' => 'datetime',
            'open' => 'decimal:6',
            'close' => 'decimal:6',
            'bid_open' => 'decimal:6',
            'bid_high' => 'decimal:6',
            'bid_low' => 'decimal:6',
            'bid_close' => 'decimal:6',
            'ask_open' => 'decimal:6',
            'ask_high' => 'decimal:6',
            'ask_low' => 'decimal:6',
            'ask_close' => 'decimal:6',
            'high' => 'decimal:6',
            'low' => 'decimal:6',
            'price_at_8' => 'decimal:6',
        ];
    }

    public function pair(): BelongsTo
    {
        return $this->belongsTo(MarketPair::class, 'pair_id');
    }

    public function timeframeType(): BelongsTo
    {
        return $this->belongsTo(TimeframeType::class, 'timeframe_type_id');
    }
}
