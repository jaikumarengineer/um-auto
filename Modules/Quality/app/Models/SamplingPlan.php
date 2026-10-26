<?php

namespace Modules\Quality\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SamplingPlan extends Model
{
    protected $fillable = [
        'code',
        'name',
        'standard_ref',
        'inspection_level',
        'aql',
        'lot_min',
        'lot_max',
        'sample_size',
        'accept_number',
        'reject_number',
        'notes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'aql' => 'decimal:3',
            'is_active' => 'boolean',
        ];
    }

    public function standards(): HasMany
    {
        return $this->hasMany(InspectionStandard::class);
    }

    public static function forQuantity(float $qty): ?self
    {
        $quantity = (int) ceil($qty);

        return static::query()
            ->where('is_active', true)
            ->where('lot_min', '<=', $quantity)
            ->where('lot_max', '>=', $quantity)
            ->orderBy('sample_size')
            ->first();
    }

    public function bandLabel(): string
    {
        return $this->lot_min.'–'.$this->lot_max;
    }
}
