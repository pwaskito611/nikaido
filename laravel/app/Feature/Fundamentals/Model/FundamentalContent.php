<?php

declare(strict_types=1);

namespace App\Feature\Fundamentals\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FundamentalContent extends Model
{
    protected $fillable = [
        'fundamental_id',
        'code',
        'name',
        'value',
        'datetime',
        'released_at',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'json',
            'datetime' => 'datetime',
            'released_at' => 'datetime',
        ];
    }

    public function fundamental(): BelongsTo
    {
        return $this->belongsTo(Fundamental::class);
    }
}
