<?php

namespace Modules\Quality\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class IncomingPart extends Model
{
    protected $fillable = [
        'part_number',
        'name',
        'description',
        'uom',
        'category_id',
        'vendor_id',
        'approved_on',
        'drawing_revision',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'approved_on' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(PartCategory::class, 'category_id');
    }

    /**
     * Legacy primary vendor column — kept in sync with the first linked vendor.
     */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(ApprovedVendor::class, 'vendor_id');
    }

    public function vendors(): BelongsToMany
    {
        return $this->belongsToMany(
            ApprovedVendor::class,
            'incoming_part_vendor',
            'incoming_part_id',
            'approved_vendor_id',
        )->withTimestamps()->orderBy('name');
    }

    public function lots(): HasMany
    {
        return $this->hasMany(IncomingLot::class, 'part_id');
    }

    public function orderLines(): HasMany
    {
        return $this->hasMany(PurchaseOrderLine::class, 'part_id');
    }

    public function standards(): HasMany
    {
        return $this->hasMany(InspectionStandard::class, 'part_id');
    }

    public function standard(): HasOne
    {
        return $this->hasOne(InspectionStandard::class, 'part_id')->latestOfMany();
    }

    public function currentStandard(): ?InspectionStandard
    {
        return $this->standards()
            ->where('status', 'active')
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first();
    }

    public function editableStandard(): ?InspectionStandard
    {
        return $this->currentStandard()
            ?? $this->standards()->orderByDesc('effective_from')->orderByDesc('id')->first();
    }

    public function label(): string
    {
        return $this->part_number.' · '.$this->name;
    }

    public function vendorsLabel(): string
    {
        $vendors = $this->relationLoaded('vendors')
            ? $this->vendors
            : $this->vendors()->get();

        if ($vendors->isEmpty()) {
            return $this->vendor?->optionLabel() ?: '—';
        }

        return $vendors->map(fn (ApprovedVendor $vendor) => $vendor->optionLabel())->implode(', ');
    }

    /**
     * @param  list<int|string>  $vendorIds
     */
    public function syncVendors(array $vendorIds): void
    {
        $ids = collect($vendorIds)
            ->filter(fn ($id) => $id !== null && $id !== '')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $this->vendors()->sync($ids);
        $this->forceFill([
            'vendor_id' => $ids[0] ?? null,
        ])->save();
    }

    public function scopeForVendor($query, int|string|null $vendorId)
    {
        if ($vendorId === null || $vendorId === '') {
            return $query->whereRaw('0 = 1');
        }

        $vendorId = (int) $vendorId;

        return $query->where(function ($query) use ($vendorId) {
            $query->where('incoming_parts.vendor_id', $vendorId)
                ->orWhereExists(function ($sub) use ($vendorId) {
                    $sub->selectRaw('1')
                        ->from('incoming_part_vendor')
                        ->whereColumn('incoming_part_vendor.incoming_part_id', 'incoming_parts.id')
                        ->where('incoming_part_vendor.approved_vendor_id', $vendorId);
                });
        });
    }
}
