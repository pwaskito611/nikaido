<?php

namespace App\Engine\Models;

use Illuminate\Database\Eloquent\Model;

class EconomicsEvent extends Model
{
    protected $table = 'economics_events';

    public $timestamps = false;

    protected $fillable = [
        'country_code',
        'point',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'point' => 'float',
        ];
    }
}
