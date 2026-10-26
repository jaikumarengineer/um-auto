<?php

namespace Modules\Quality\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Quality\Support\Qty;

class PurchaseInvoiceLine extends Model
{
    protected $fillable = [
        'purchase_invoice_id',
        'purchase_order_line_id',
        'part_id',
        'invoiced_qty',
    ];

    protected function casts(): array
    {
        return [
            'invoiced_qty' => 'decimal:3',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(PurchaseInvoice::class, 'purchase_invoice_id');
    }

    public function purchaseOrderLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderLine::class);
    }

    public function part(): BelongsTo
    {
        return $this->belongsTo(IncomingPart::class, 'part_id');
    }

    public function lots(): HasMany
    {
        return $this->hasMany(IncomingLot::class);
    }

    public function lotQuantity(): float
    {
        $sum = $this->relationLoaded('lots')
            ? $this->lots->sum(fn (IncomingLot $lot) => (float) $lot->qty)
            : $this->lots()->sum('qty');

        return Qty::num($sum);
    }
}
