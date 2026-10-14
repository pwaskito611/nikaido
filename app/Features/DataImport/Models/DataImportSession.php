<?php

declare(strict_types=1);

namespace App\Features\DataImport\Models;

use App\Features\Engine\Models\MarketPair;
use App\Features\Engine\Models\TimeframeType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DataImportSession extends Model
{
    protected $table = 'data_import_session';

    protected $fillable = [
        'pair_id',
        'timeframe_type_id',
        'token_hash',
        'status',
        'started_at',
        'stopped_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'stopped_at' => 'datetime',
        ];
    }

    public function pair(): BelongsTo
    {
        return $this->belongsTo(MarketPair::class, 'pair_id');
    }

    public function timeframeType(): BelongsTo
    {
        return $this->belongsTo(TimeframeType::class, 'timeframe_type_id');
    }
}
