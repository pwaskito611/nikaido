<?php

namespace App\Engine\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketType extends Model
{
    protected $table = 'market_type';

    public $timestamps = false;

    protected $fillable = [
        'name',
    ];

    public function marketPairs(): HasMany
    {
        return $this->hasMany(MarketPair::class, 'market_type_id');
    }
}
