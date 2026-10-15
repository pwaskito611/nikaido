<?php

declare(strict_types=1);

namespace App\Feature\MarketTypes\Model;

use App\Feature\MarketPairs\Model\MarketPair;
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
