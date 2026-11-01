<?php

namespace Modules\Quality\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InspectionStandardRevision extends Model
{
    protected $fillable = [
        'inspection_standard_id',
        'sequence',
        'rev_no',
        'rev_date',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'rev_date' => 'date',
        ];
    }

    public function standard(): BelongsTo
    {
        return $this->belongsTo(InspectionStandard::class, 'inspection_standard_id');
    }
}
