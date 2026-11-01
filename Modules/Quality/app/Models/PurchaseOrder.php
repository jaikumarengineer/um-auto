<?php

namespace Modules\Quality\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Modules\Quality\Concerns\HasRecordActivities;
use Modules\Quality\Support\Qty;

class PurchaseOrder extends Model
{
    use HasRecordActivities;

    protected $fillable = [
        'po_number',
        'vendor_id',
        'po_date',
        'expected_date',
        'status',
        'currency',
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
            'po_date' => 'date',
            'expected_date' => 'date',
            'approved_at' => 'datetime',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(ApprovedVendor::class, 'vendor_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseOrderLine::class)->orderBy('line_no');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(PurchaseInvoice::class)->orderBy('invoice_date');
    }

    public function invoiceLines(): HasManyThrough
    {
        return $this->hasManyThrough(
            PurchaseInvoiceLine::class,
            PurchaseOrderLine::class,
            'purchase_order_id',
            'purchase_order_line_id',
        );
    }

    public function orderedQuantity(): float
    {
        $sum = $this->relationLoaded('lines')
            ? $this->lines->sum(fn (PurchaseOrderLine $line) => (float) $line->ordered_qty)
            : $this->lines()->sum('ordered_qty');

        return Qty::num($sum);
    }

    public function invoicedQuantity(): float
    {
        if ($this->relationLoaded('lines') && $this->lines->every(fn ($line) => $line->relationLoaded('invoiceLines'))) {
            return Qty::num($this->lines->sum(fn (PurchaseOrderLine $line) => $line->invoicedQuantity()));
        }

        return Qty::num($this->invoiceLines()->sum('invoiced_qty'));
    }

    public function balanceQuantity(): float
    {
        return max(0, Qty::num($this->orderedQuantity() - $this->invoicedQuantity()));
    }

    public function isOverdue(): bool
    {
        return $this->expected_date
            && $this->expected_date->lt(now()->startOfDay())
            && in_array($this->status, ['open', 'partial'], true);
    }

    public function syncFulfillmentStatus(): void
    {
        if (in_array($this->status, ['draft', 'cancelled'], true)) {
            return;
        }

        $this->load(['lines.invoiceLines']);

        $any = false;
        $complete = $this->lines->isNotEmpty();

        foreach ($this->lines as $line) {
            $invoiced = $line->invoicedQuantity();

            if ($invoiced > 0) {
                $any = true;
            }

            if (Qty::exceeds($line->ordered_qty, $invoiced) && ! Qty::same($line->ordered_qty, $invoiced)) {
                $complete = false;
            }
        }

        $this->status = match (true) {
            $complete && $any => 'closed',
            $any => 'partial',
            default => 'open',
        };

        $this->save();
    }
}
