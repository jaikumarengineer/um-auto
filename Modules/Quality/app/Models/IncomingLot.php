<?php

namespace Modules\Quality\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IncomingLot extends Model
{
    protected $fillable = [
        'lot_number',
        'purchase_invoice_line_id',
        'part_id',
        'vendor_id',
        'qty',
        'received_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:3',
            'received_at' => 'datetime',
        ];
    }

    public function invoiceLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseInvoiceLine::class, 'purchase_invoice_line_id');
    }

    public function part(): BelongsTo
    {
        return $this->belongsTo(IncomingPart::class, 'part_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(ApprovedVendor::class, 'vendor_id');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(InspectionReport::class)->orderByDesc('inspected_at');
    }

    public function latestReport(): ?InspectionReport
    {
        return $this->relationLoaded('reports')
            ? $this->reports->sortByDesc('inspected_at')->first()
            : $this->reports()->first();
    }

    public function applyResult(string $result): void
    {
        $this->status = match ($result) {
            'fail' => 'rejected',
            'conditional' => 'partial',
            default => 'accepted',
        };

        $this->save();
    }
}
