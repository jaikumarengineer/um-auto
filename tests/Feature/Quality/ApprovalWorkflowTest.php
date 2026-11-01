<?php

use App\Models\User;
use App\Services\RecordApproval;
use Livewire\Volt\Volt;
use Modules\Quality\Database\Seeders\QualityDatabaseSeeder;
use Modules\Quality\Models\ApprovedVendor;
use Modules\Quality\Models\IncomingLot;
use Modules\Quality\Models\IncomingPart;
use Modules\Quality\Models\InspectionReport;
use Modules\Quality\Models\PurchaseOrder;
use Modules\Quality\Models\RecordActivity;
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
        ->and($order->approval_status)->toBe(RecordApproval::PENDING)
        ->and($order->activities()->where('event', RecordActivity::EVENT_CREATED)->count())->toBe(1);

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
        ->and($order->approved_by)->toBe($manager->id)
        ->and($order->activities()->where('event', RecordActivity::EVENT_APPROVAL_STATUS_CHANGED)->count())->toBe(1);

    $this->get(route('admin::quality.purchase.orders.show', $order))
        ->assertOk()
        ->assertSee(__('Activity'))
        ->assertSee(__('created this record'))
        ->assertSee(__('changed accepted status from :from to :to', [
            'from' => __('Pending'),
            'to' => __('Approved'),
        ]));
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

it('lets managers set accepted status on inspection reports from the list', function () {
    $this->seed(QualityDatabaseSeeder::class);

    $incoming = User::factory()->create([
        'user_type' => 'incoming',
        'username' => 'incoming-report-approver',
    ]);
    $manager = User::factory()->create([
        'user_type' => 'manager',
        'username' => 'manager-report-approver',
    ]);

    $lot = IncomingLot::query()->where('lot_number', 'L-BRK-150B')->firstOrFail();

    $this->actingAs($incoming);

    $report = app(QualityWorkflow::class)->saveInspectionReport([
        'lot_id' => $lot->id,
        'sample_size' => 20,
        'accepted_qty' => $lot->qty,
        'supplier_decision' => 'conforming',
        'umac_decision' => 'conforming',
        'inspected_at' => now()->format('Y-m-d H:i:s'),
        'remarks' => null,
        'assembly_name' => null,
        'process' => null,
        'bill_no' => null,
        'images' => [],
        'inspected_by' => $incoming->id,
    ], []);

    expect($report->approval_status)->toBe(RecordApproval::PENDING)
        ->and($report->created_by)->toBe($incoming->id);

    $this->actingAs($manager);

    Volt::test('admin.quality.incoming.reports-page')
        ->assertSee(__('Accepted Status'))
        ->call('openAcceptedStatus', $report->id)
        ->set('acceptedStatus', RecordApproval::APPROVED)
        ->call('saveAcceptedStatus')
        ->assertHasNoErrors();

    $report = $report->fresh();

    expect($report->approval_status)->toBe(RecordApproval::APPROVED)
        ->and($report->approved_by)->toBe($manager->id)
        ->and($report->verified_by)->toBe($manager->id)
        ->and(InspectionReport::query()->whereKey($report->id)->value('approval_status'))
        ->toBe(RecordApproval::APPROVED);

    $this->get(route('admin::quality.incoming.reports.show', $report))
        ->assertOk()
        ->assertSee(__('Decided by :name on :date', [
            'name' => $manager->full_name,
            'date' => $report->approved_at?->format('d M Y H:i') ?? '—',
        ]));
});
