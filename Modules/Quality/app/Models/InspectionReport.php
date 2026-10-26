<?php

namespace Modules\Quality\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InspectionReport extends Model
{
    protected $fillable = [
        'report_number',
        'incoming_lot_id',
        'inspection_standard_id',
        'sampling_plan_id',
        'sample_size',
        'inspected_qty',
        'accepted_qty',
        'rejected_qty',
        'result',
        'inspected_at',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'inspected_qty' => 'decimal:3',
            'accepted_qty' => 'decimal:3',
            'rejected_qty' => 'decimal:3',
            'inspected_at' => 'datetime',
        ];
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(IncomingLot::class, 'incoming_lot_id');
    }

    public function standard(): BelongsTo
    {
        return $this->belongsTo(InspectionStandard::class, 'inspection_standard_id');
    }

    public function samplingPlan(): BelongsTo
    {
        return $this->belongsTo(SamplingPlan::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(InspectionResult::class);
    }
}
