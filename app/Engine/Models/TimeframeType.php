<?php

declare(strict_types=1);

namespace App\Engine\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TimeframeType extends Model
{
    protected $table = 'timeframe_type';

    public $timestamps = false;

    protected $fillable = [
        'name',
    ];

    public function marketPrices(): HasMany
    {
        return $this->hasMany(MarketPrice::class, 'timeframe_type_id');
    }
}
