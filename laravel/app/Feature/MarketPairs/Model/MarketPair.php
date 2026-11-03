<?php

declare(strict_types=1);

namespace App\Feature\MarketPairs\Model;

use App\Feature\Fundamentals\Model\Fundamental;
use App\Feature\MarketPrice\Model\MarketPrice;
use App\Feature\MarketTypes\Model\MarketType;
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
    ];

    protected $casts = [
        'contract_size' => 'decimal:8',
    ];

    public function marketType(): BelongsTo
    {
        return $this->belongsTo(MarketType::class, 'market_type_id');
    }

    public function prices(): HasMany
    {
        return $this->hasMany(MarketPrice::class, 'pair_id');
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
}
