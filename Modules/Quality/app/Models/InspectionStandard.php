<?php

namespace Modules\Quality\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InspectionStandard extends Model
{
    protected $fillable = [
        'code',
        'part_id',
        'sampling_plan_id',
        'revision',
        'title',
        'effective_from',
        'status',
        'notes',
        'supplier_name',
        'supplier_code',
        'doc_no',
        'page_label',
        'drawing_no',
        'drawing_rev',
        'approved_by',
        'checked_by',
        'prepared_by',
        'document_path',
        'document_name',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
        ];
    }

    public function part(): BelongsTo
    {
        return $this->belongsTo(IncomingPart::class, 'part_id');
    }

    public function samplingPlan(): BelongsTo
    {
        return $this->belongsTo(SamplingPlan::class);
    }

    public function characteristics(): HasMany
    {
        return $this->hasMany(InspectionCharacteristic::class)->orderBy('sequence');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(InspectionStandardRevision::class)->orderBy('sequence');
    }
}
