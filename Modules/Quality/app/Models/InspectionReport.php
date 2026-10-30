<?php

namespace Modules\Quality\Models;

use App\Models\User;
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
        'assembly_name',
        'process',
        'bill_no',
        'supplier_status',
        'umac_status',
        'supplier_decision',
        'umac_decision',
        'inspected_by',
        'verified_by',
        'images',
        'created_by',
        'approval_status',
        'approved_by',
        'approved_at',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'inspected_qty' => 'decimal:3',
            'accepted_qty' => 'decimal:3',
            'rejected_qty' => 'decimal:3',
            'inspected_at' => 'datetime',
            'approved_at' => 'datetime',
            'images' => 'array',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
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

    public function inspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspected_by');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
