<?php

declare(strict_types=1);

namespace App\Features\Engine\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
}
