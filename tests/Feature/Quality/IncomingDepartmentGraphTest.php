<?php

use Modules\Quality\Models\ApprovedVendor;
use Modules\Quality\Models\IncomingLot;
use Modules\Quality\Models\IncomingPart;
use Modules\Quality\Models\InspectionReport;
use Modules\Quality\Models\PurchaseOrder;
use Modules\Quality\Services\QualityAnalysis;
use Modules\Quality\Services\QualityWorkflow;

it('buckets incoming graphs by day month and year within the date range', function () {
    $vendor = ApprovedVendor::query()->create([
        'code' => 'V-GRAPH',
        'name' => 'Graph Vendor',
        'status' => 'approved',
    ]);
    $part = IncomingPart::query()->create([
        'part_number' => 'GRAPH-PART-1',
        'name' => 'Graph Part',
        'uom' => 'PCS',
    ]);
    $order = PurchaseOrder::query()->create([
        'po_number' => 'PO-GRAPH-1',
        'vendor_id' => $vendor->id,
        'po_date' => now()->toDateString(),
        'status' => 'open',
        'currency' => 'INR',
    ]);
    $line = $order->lines()->create([
        'part_id' => $part->id,
        'line_no' => 1,
        'ordered_qty' => 14,
        'uom' => 'PCS',
        'unit_price' => 10,
    ]);

    app(QualityWorkflow::class)->savePurchaseInvoice([
        'invoice_number' => 'PI-GRAPH-1',
        'purchase_order_id' => $order->id,
        'invoice_date' => now()->toDateString(),
        'remarks' => null,
    ], [[
        'purchase_order_line_id' => $line->id,
        'invoiced_qty' => 14,
        'lots' => [
            ['lot_number' => 'LOT-GRAPH-1', 'qty' => 10],
            ['lot_number' => 'LOT-GRAPH-2', 'qty' => 4],
        ],
    ]]);

    $dayOne = now()->startOfMonth()->addDays(1);
    $dayTwo = now()->startOfMonth()->addDays(2);

    $inspectedLot = IncomingLot::query()->where('lot_number', 'LOT-GRAPH-1')->firstOrFail();
    $waitingLot = IncomingLot::query()->where('lot_number', 'LOT-GRAPH-2')->firstOrFail();

    $inspectedLot->update(['received_at' => $dayOne, 'status' => 'accepted']);
    $waitingLot->update(['received_at' => $dayTwo, 'status' => 'waiting']);

    InspectionReport::query()->create([
        'report_number' => 'IR-GRAPH-1',
        'incoming_lot_id' => $inspectedLot->id,
        'result' => 'pass',
        'inspected_at' => $dayOne,
        'inspected_qty' => 10,
        'accepted_qty' => 10,
        'rejected_qty' => 0,
    ]);

    $analysis = app(QualityAnalysis::class);
    $from = now()->startOfMonth()->toDateString();
    $to = now()->toDateString();

    $current = $analysis->departmentGraph('incoming', 'current', $from, $to);
    expect($current)->not->toBeNull()
        ->and($current['type'])->toBe('bar')
        ->and($current['title'])->toBe('Parts coming vs inspected')
        ->and($current['subtitle'])->toContain('Day')
        ->and($current['dataset']['labels'])->toContain($dayOne->format('d M'))
        ->and($current['dataset']['labels'])->not->toContain('GRAPH-PART-1')
        ->and($current['dataset']['datasets'][0]['label'])->toBe('Qty received')
        ->and($current['dataset']['datasets'][1]['label'])->toBe('Qty inspected');

    $dayOneIndex = array_search($dayOne->format('d M'), $current['dataset']['labels'], true);
    $dayTwoIndex = array_search($dayTwo->format('d M'), $current['dataset']['labels'], true);

    expect($dayOneIndex)->not->toBeFalse()
        ->and($dayTwoIndex)->not->toBeFalse()
        ->and($current['dataset']['datasets'][0]['data'][$dayOneIndex])->toEqual(10.0)
        ->and($current['dataset']['datasets'][1]['data'][$dayOneIndex])->toEqual(10.0)
        ->and($current['dataset']['datasets'][0]['data'][$dayTwoIndex])->toEqual(4.0)
        ->and($current['dataset']['datasets'][1]['data'][$dayTwoIndex])->toEqual(0.0);

    $month = $analysis->departmentGraph(
        'incoming',
        'month',
        now()->subMonths(2)->startOfMonth()->toDateString(),
        now()->toDateString(),
    );
    expect($month['type'])->toBe('line')
        ->and($month['subtitle'])->toContain('Month')
        ->and($month['dataset']['labels'])->toContain(now()->format('M Y'))
        ->and($month['dataset']['labels'])->not->toContain('GRAPH-PART-1');

    $year = $analysis->departmentGraph(
        'incoming',
        'year',
        now()->subYear()->startOfYear()->toDateString(),
        now()->toDateString(),
    );
    expect($year['type'])->toBe('bar')
        ->and($year['subtitle'])->toContain('Year')
        ->and($year['dataset']['labels'])->toContain(now()->format('Y'))
        ->and($year['dataset']['labels'])->not->toContain('GRAPH-PART-1');
});
