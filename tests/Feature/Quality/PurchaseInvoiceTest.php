<?php

use Illuminate\Validation\ValidationException;
use Modules\Quality\Models\ApprovedVendor;
use Modules\Quality\Models\IncomingLot;
use Modules\Quality\Models\IncomingPart;
use Modules\Quality\Models\PurchaseInvoice;
use Modules\Quality\Models\PurchaseOrder;
use Modules\Quality\Services\QualityWorkflow;

it('rejects an invoice quantity above the open purchase order balance', function () {
    [$order, $line] = qualityOrder(100);

    expect(fn () => app(QualityWorkflow::class)->savePurchaseInvoice([
        'invoice_number' => 'PI-OVER',
        'purchase_order_id' => $order->id,
        'invoice_date' => now()->toDateString(),
        'remarks' => null,
    ], [[
        'purchase_order_line_id' => $line->id,
        'invoiced_qty' => 101,
        'lots' => [['lot_number' => 'LOT-1', 'qty' => 101]],
    ]]))->toThrow(ValidationException::class);

    expect(PurchaseInvoice::query()->count())->toBe(0);
});

it('requires batch quantities to add up to the invoiced quantity', function () {
    [$order, $line] = qualityOrder(100);

    expect(fn () => app(QualityWorkflow::class)->savePurchaseInvoice([
        'invoice_number' => 'PI-LOTS',
        'purchase_order_id' => $order->id,
        'invoice_date' => now()->toDateString(),
        'remarks' => null,
    ], [[
        'purchase_order_line_id' => $line->id,
        'invoiced_qty' => 40,
        'lots' => [
            ['lot_number' => 'LOT-A', 'qty' => 10],
            ['lot_number' => 'LOT-B', 'qty' => 10],
        ],
    ]]))->toThrow(ValidationException::class);
});

it('books several invoices and batches against one purchase order', function () {
    [$order, $line] = qualityOrder(100);
    $workflow = app(QualityWorkflow::class);

    $first = $workflow->savePurchaseInvoice([
        'invoice_number' => 'PI-1',
        'purchase_order_id' => $order->id,
        'invoice_date' => now()->toDateString(),
        'remarks' => null,
    ], [[
        'purchase_order_line_id' => $line->id,
        'invoiced_qty' => 60,
        'lots' => [
            ['lot_number' => 'lot-a', 'qty' => 25],
            ['lot_number' => 'LOT-B', 'qty' => 35],
        ],
    ]]);

    $workflow->savePurchaseInvoice([
        'invoice_number' => 'PI-2',
        'purchase_order_id' => $order->id,
        'invoice_date' => now()->toDateString(),
        'remarks' => null,
    ], [[
        'purchase_order_line_id' => $line->id,
        'invoiced_qty' => 40,
        'lots' => [['lot_number' => 'LOT-C', 'qty' => 40]],
    ]]);

    $order->refresh();

    expect($first->lines)->toHaveCount(1)
        ->and(IncomingLot::query()->pluck('lot_number')->all())->toEqualCanonicalizing(['LOT-A', 'LOT-B', 'LOT-C'])
        ->and($order->status)->toBe('closed')
        ->and($order->balanceQuantity())->toEqual(0.0);
});

function qualityOrder(float $qty): array
{
    $vendor = ApprovedVendor::query()->create([
        'code' => 'V-1',
        'name' => 'Apex',
        'status' => 'approved',
    ]);
    $part = IncomingPart::query()->create([
        'part_number' => 'BRK-1',
        'name' => 'Pad',
        'uom' => 'PCS',
    ]);
    $order = PurchaseOrder::query()->create([
        'po_number' => 'PO-1',
        'vendor_id' => $vendor->id,
        'po_date' => now()->toDateString(),
        'status' => 'open',
        'currency' => 'INR',
    ]);
    $line = $order->lines()->create([
        'part_id' => $part->id,
        'line_no' => 1,
        'ordered_qty' => $qty,
        'uom' => 'PCS',
        'unit_price' => 10,
    ]);

    return [$order, $line];
}
