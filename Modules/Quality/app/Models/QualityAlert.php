<?php

namespace Modules\Quality\Models;

use Illuminate\Database\Eloquent\Model;

class QualityAlert extends Model
{
    protected $fillable = [
        'channel',
        'alert_type',
        'recipient',
        'subject',
        'body',
        'status',
        'alert_on',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'alert_on' => 'date',
            'meta' => 'array',
        ];
    }
}
