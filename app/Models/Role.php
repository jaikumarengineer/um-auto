<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function displayName(): string
    {
        return str($this->name)->replace(['_', '-'], ' ')->title()->toString();
    }

    public function scopeEditable(Builder $query): Builder
    {
        return $query->where('name', '!=', 'admin');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
