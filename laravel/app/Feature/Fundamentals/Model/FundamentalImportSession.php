<?php

declare(strict_types=1);

namespace App\Feature\Fundamentals\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FundamentalImportSession extends Model
{
    protected $fillable = [
        'fundamental_id',
        'active_fundamental_id',
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

    public function fundamental(): BelongsTo
    {
        return $this->belongsTo(Fundamental::class);
    }
}
