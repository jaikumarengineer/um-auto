<?php

namespace Modules\Quality\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IncomingPart extends Model
{
    protected $fillable = [
        'part_number',
        'name',
        'description',
        'uom',
        'category_id',
        'vendor_id',
        'drawing_revision',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(PartCategory::class, 'category_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(ApprovedVendor::class, 'vendor_id');
    }

    public function standards(): HasMany
    {
        return $this->hasMany(InspectionStandard::class, 'part_id');
    }

    public function currentStandard(): ?InspectionStandard
    {
        return $this->standards()
            ->where('status', 'active')
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first();
    }

    public function label(): string
    {
        return $this->part_number.' · '.$this->name;
    }
}
