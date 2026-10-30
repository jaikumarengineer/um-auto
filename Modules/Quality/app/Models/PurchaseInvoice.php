<?php

namespace Modules\Quality\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Modules\Quality\Support\Qty;

class PurchaseInvoice extends Model
{
    protected $fillable = [
        'invoice_number',
        'purchase_order_id',
        'vendor_id',
        'invoice_date',
        'status',
        'remarks',
        'created_by',
        'approval_status',
        'approved_by',
        'approved_at',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
            'approved_at' => 'datetime',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(ApprovedVendor::class, 'vendor_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseInvoiceLine::class);
    }

    public function lots(): HasManyThrough
    {
        return $this->hasManyThrough(
            IncomingLot::class,
            PurchaseInvoiceLine::class,
            'purchase_invoice_id',
            'purchase_invoice_line_id',
        );
    }

    public function invoicedQuantity(): float
    {
        $sum = $this->relationLoaded('lines')
            ? $this->lines->sum(fn (PurchaseInvoiceLine $line) => (float) $line->invoiced_qty)
            : $this->lines()->sum('invoiced_qty');

        return Qty::num($sum);
    }

    public function hasInspectionReports(): bool
    {
        return $this->lots()->whereHas('reports')->exists();
    }
}
