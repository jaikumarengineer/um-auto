<?php

namespace Modules\Quality\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PartCategory extends Model
{
    protected $fillable = [
        'name',
    ];

    public function parts(): HasMany
    {
        return $this->hasMany(IncomingPart::class, 'category_id');
    }
}
