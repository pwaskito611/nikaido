<?php

declare(strict_types=1);

namespace App\Feature\News\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NewsItem extends Model
{
    protected $fillable = [
        'source',
        'external_id',
        'dedupe_key',
        'title',
        'content',
        'published_at',
        'available_at',
        'language',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'available_at' => 'datetime',
        ];
    }

    public function impacts(): HasMany
    {
        return $this->hasMany(NewsMarketPairImpact::class);
    }
}
