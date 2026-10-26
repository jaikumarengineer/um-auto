<?php

namespace Modules\Quality\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InspectionResult extends Model
{
    protected $fillable = [
        'inspection_report_id',
        'inspection_characteristic_id',
        'characteristic_name',
        'specification',
        'measured_value',
        'result',
        'is_critical',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'is_critical' => 'boolean',
        ];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(InspectionReport::class, 'inspection_report_id');
    }

    public function characteristic(): BelongsTo
    {
        return $this->belongsTo(InspectionCharacteristic::class, 'inspection_characteristic_id');
    }
}
