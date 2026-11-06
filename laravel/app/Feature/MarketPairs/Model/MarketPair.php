<?php

declare(strict_types=1);

namespace App\Feature\MarketPairs\Model;

use App\Feature\CashFlows\Model\MarketPairCashFlow;
use App\Feature\Fundamentals\Model\Fundamental;
use App\Feature\MarketPrice\Model\MarketPrice;
use App\Feature\MarketTypes\Model\MarketType;
use App\Feature\News\Model\NewsMarketPairImpact;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketPair extends Model
{
    protected $table = 'market_pair';

    public $timestamps = false;

    protected $fillable = [
        'market_type_id',
        'base',
        'quote',
        'contract_size',
        'quantity_unit',
        'tick_size',
        'tick_value',
        'minimum_quantity',
        'quantity_step',
        'initial_margin_rate',
    ];

    protected $casts = [
        'contract_size' => 'decimal:8',
        'tick_size' => 'decimal:10',
        'tick_value' => 'decimal:10',
        'minimum_quantity' => 'decimal:8',
        'quantity_step' => 'decimal:8',
        'initial_margin_rate' => 'decimal:4',
    ];

    public function marketType(): BelongsTo
    {
        return $this->belongsTo(MarketType::class, 'market_type_id');
    }

    public function prices(): HasMany
    {
        return $this->hasMany(MarketPrice::class, 'pair_id');
    }

    public function cashFlows(): HasMany
    {
        return $this->hasMany(MarketPairCashFlow::class);
    }

    public function relatedPairs(): BelongsToMany
    {
        return $this->belongsToMany(
            self::class,
            'market_pair_related_pairs',
            'market_pair_id',
            'related_market_pair_id',
        )->withPivot('timeframe_type_id')->withTimestamps();
    }

    public function fundamentals(): BelongsToMany
    {
        return $this->belongsToMany(
            Fundamental::class,
            'fundamental_related',
            'pair_id',
            'fundamental_id',
        )->withTimestamps();
    }

    public function newsImpacts(): HasMany
    {
        return $this->hasMany(NewsMarketPairImpact::class);
    }
}
