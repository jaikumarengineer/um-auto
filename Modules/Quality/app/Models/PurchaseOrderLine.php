<?php

namespace Modules\Quality\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Quality\Support\Qty;

class PurchaseOrderLine extends Model
{
    protected $fillable = [
        'purchase_order_id',
        'part_id',
        'line_no',
        'ordered_qty',
        'uom',
        'unit_price',
    ];

    protected function casts(): array
    {
        return [
            'ordered_qty' => 'decimal:3',
            'unit_price' => 'decimal:2',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function part(): BelongsTo
    {
        return $this->belongsTo(IncomingPart::class, 'part_id');
    }

    public function invoiceLines(): HasMany
    {
        return $this->hasMany(PurchaseInvoiceLine::class);
    }

    public function invoicedQuantity(?int $exceptInvoiceId = null): float
    {
        if ($this->relationLoaded('invoiceLines') && $exceptInvoiceId === null) {
            return Qty::num($this->invoiceLines->sum(fn ($line) => (float) $line->invoiced_qty));
        }

        $query = $this->invoiceLines();

        if ($exceptInvoiceId) {
            $query->where('purchase_invoice_id', '!=', $exceptInvoiceId);
        }

        return Qty::num($query->sum('invoiced_qty'));
    }

    public function remainingQuantity(?int $exceptInvoiceId = null): float
    {
        return max(0, Qty::num((float) $this->ordered_qty - $this->invoicedQuantity($exceptInvoiceId)));
    }

    public function amount(): float
    {
        return round((float) $this->ordered_qty * (float) $this->unit_price, 2);
    }
}
