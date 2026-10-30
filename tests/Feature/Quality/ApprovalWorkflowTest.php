<?php

use App\Models\User;
use App\Services\RecordApproval;
use Modules\Quality\Models\ApprovedVendor;
use Modules\Quality\Models\IncomingPart;
use Modules\Quality\Models\PurchaseOrder;
use Modules\Quality\Services\QualityWorkflow;

it('marks department purchase orders as pending until a manager approves them', function () {
    $purchase = User::factory()->create([
        'user_type' => 'purchase',
        'username' => 'purchase-approver',
    ]);
    $manager = User::factory()->create([
        'user_type' => 'manager',
        'username' => 'manager-approver',
    ]);

    $vendor = ApprovedVendor::query()->create([
        'code' => 'V-APPR',
        'name' => 'Approval Vendor',
        'status' => 'approved',
    ]);
    $part = IncomingPart::query()->create([
        'part_number' => 'APPR-PART',
        'name' => 'Approval Part',
        'uom' => 'PCS',
    ]);

    $this->actingAs($purchase);

    $order = app(QualityWorkflow::class)->savePurchaseOrder([
        'po_number' => 'PO-APPR-1',
        'vendor_id' => $vendor->id,
        'po_date' => now()->toDateString(),
        'expected_date' => null,
        'currency' => 'INR',
        'remarks' => null,
        'status' => 'open',
    ], [[
        'part_id' => $part->id,
        'ordered_qty' => 5,
        'uom' => 'PCS',
        'unit_price' => 10,
    ]]);

    expect($order->created_by)->toBe($purchase->id)
        ->and($order->approval_status)->toBe(RecordApproval::PENDING);

    expect(fn () => app(QualityWorkflow::class)->savePurchaseInvoice([
        'invoice_number' => 'PI-APPR-1',
        'purchase_order_id' => $order->id,
        'invoice_date' => now()->toDateString(),
        'remarks' => null,
    ], [[
        'purchase_order_line_id' => $order->lines()->first()->id,
        'invoiced_qty' => 5,
        'lots' => [['lot_number' => 'LOT-APPR-1', 'qty' => 5]],
    ]]))->toThrow(Illuminate\Validation\ValidationException::class);

    $this->actingAs($manager);
    $order = app(QualityWorkflow::class)->approvePurchaseOrder($order->fresh());

    expect($order->approval_status)->toBe(RecordApproval::APPROVED)
        ->and($order->approved_by)->toBe($manager->id);

    $this->get(route('admin::quality.purchase.orders.show', $order))->assertOk();
});

it('auto-approves records created by managers', function () {
    $manager = User::factory()->create([
        'user_type' => 'manager',
        'username' => 'manager-create',
    ]);
    $vendor = ApprovedVendor::query()->create([
        'code' => 'V-MGR',
        'name' => 'Manager Vendor',
        'status' => 'approved',
    ]);
    $part = IncomingPart::query()->create([
        'part_number' => 'MGR-PART',
        'name' => 'Manager Part',
        'uom' => 'PCS',
    ]);

    $this->actingAs($manager);

    $order = app(QualityWorkflow::class)->savePurchaseOrder([
        'po_number' => 'PO-MGR-1',
        'vendor_id' => $vendor->id,
        'po_date' => now()->toDateString(),
        'expected_date' => null,
        'currency' => 'INR',
        'remarks' => null,
        'status' => 'open',
    ], [[
        'part_id' => $part->id,
        'ordered_qty' => 2,
        'uom' => 'PCS',
        'unit_price' => 8,
    ]]);

    expect($order->approval_status)->toBe(RecordApproval::APPROVED)
        ->and($order->created_by)->toBe($manager->id)
        ->and(PurchaseOrder::query()->whereKey($order->id)->value('approved_by'))->toBe($manager->id);
});
