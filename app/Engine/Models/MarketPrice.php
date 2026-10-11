<?php

namespace App\Engine\Models;

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
        'bid',
        'ask',
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
            'bid' => 'decimal:6',
            'ask' => 'decimal:6',
            'high' => 'decimal:6',
            'low' => 'decimal:6',
            'price_at_8' => 'decimal:6',
        ];
    }

    public function pair(): BelongsTo
    {
        return $this->belongsTo(MarketPair::class, 'pair_id');
    }
}
