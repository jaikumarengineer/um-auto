<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MediaStorage extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'extension',
        'path',
        'url',
        'disk',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function publicUrl(): string
    {
        if (str_starts_with($this->url, 'http://') || str_starts_with($this->url, 'https://') || str_starts_with($this->url, 'data:')) {
            return $this->url;
        }

        return asset(ltrim($this->url, '/'));
    }
}
