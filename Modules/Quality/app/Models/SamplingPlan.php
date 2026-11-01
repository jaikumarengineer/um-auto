<?php

namespace Modules\Quality\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Quality\Support\Ml08SamplingPlan;

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

    public static function forQuantity(float $qty, float|string $aql = 1): ?self
    {
        $quantity = (int) ceil($qty);

        return static::query()
            ->where('is_active', true)
            ->where('aql', $aql)
            ->where('lot_min', '<=', $quantity)
            ->where('lot_max', '>=', $quantity)
            ->orderByDesc('lot_min')
            ->first();
    }

    public function inspectsEntireLot(): bool
    {
        return (int) $this->sample_size === 0;
    }

    public function sampleSizeFor(int $quantity): int
    {
        $quantity = max(1, $quantity);

        if ($this->inspectsEntireLot()) {
            return min($quantity, max(1, (int) $this->lot_max));
        }

        return min($quantity, max(1, (int) $this->sample_size));
    }

    public function sampleLabel(): string
    {
        return $this->inspectsEntireLot() ? '*' : (string) $this->sample_size;
    }

    public function aqlLabel(): string
    {
        return Ml08SamplingPlan::formatAql($this->aql);
    }

    public function bandLabel(): string
    {
        if ((int) $this->lot_max >= Ml08SamplingPlan::OPEN_LOT) {
            return $this->lot_min.'+';
        }

        return $this->lot_min.'–'.$this->lot_max;
    }
}
