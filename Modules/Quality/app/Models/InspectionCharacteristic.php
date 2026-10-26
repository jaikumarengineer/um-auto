<?php

namespace Modules\Quality\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InspectionCharacteristic extends Model
{
    protected $fillable = [
        'inspection_standard_id',
        'sequence',
        'name',
        'specification',
        'method',
        'instrument',
        'tolerance_min',
        'tolerance_max',
        'unit',
        'is_critical',
    ];

    protected function casts(): array
    {
        return [
            'tolerance_min' => 'decimal:4',
            'tolerance_max' => 'decimal:4',
            'is_critical' => 'boolean',
        ];
    }

    public function standard(): BelongsTo
    {
        return $this->belongsTo(InspectionStandard::class, 'inspection_standard_id');
    }

    public function judge(mixed $measured): ?string
    {
        if ($this->tolerance_min === null || $this->tolerance_max === null || ! is_numeric($measured)) {
            return null;
        }

        $value = (float) $measured;

        return $value >= (float) $this->tolerance_min && $value <= (float) $this->tolerance_max
            ? 'ok'
            : 'ng';
    }
}
