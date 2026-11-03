<?php

declare(strict_types=1);

namespace App\Feature\Fundamentals\Model;

use App\Feature\MarketPairs\Model\MarketPair;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Fundamental extends Model
{
    protected $fillable = ['name', 'description', 'category'];

    public function contents(): HasMany
    {
        return $this->hasMany(FundamentalContent::class);
    }

    public function marketPairs(): BelongsToMany
    {
        return $this->belongsToMany(
            MarketPair::class,
            'fundamental_related',
            'fundamental_id',
            'pair_id',
        )->withTimestamps();
    }
}
