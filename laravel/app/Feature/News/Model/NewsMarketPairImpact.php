<?php

declare(strict_types=1);

namespace App\Feature\News\Model;

use App\Feature\MarketPairs\Model\MarketPair;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NewsMarketPairImpact extends Model
{
    protected $fillable = [
        'news_item_id',
        'market_pair_id',
        'relevance_score',
        'impact_score',
        'impact_lag_seconds',
        'impact_duration_seconds',
        'confidence_score',
        'assessment_method',
    ];

    public function newsItem(): BelongsTo
    {
        return $this->belongsTo(NewsItem::class);
    }

    public function marketPair(): BelongsTo
    {
        return $this->belongsTo(MarketPair::class);
    }
}
