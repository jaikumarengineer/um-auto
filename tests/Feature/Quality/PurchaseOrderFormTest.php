<?php

use App\Models\User;
use Livewire\Volt\Volt;
use Modules\Quality\Models\ApprovedVendor;
use Modules\Quality\Models\IncomingPart;
use Modules\Quality\Models\PurchaseOrder;

it('saves a purchase order after adding and removing a blank line', function () {
    $this->actingAs(User::factory()->create([
        'user_type' => 'admin',
        'username' => 'po-form-admin',
    ]));

    $vendor = ApprovedVendor::query()->create([
        'code' => 'V-4401',
        'name' => 'Apex Precision Castings',
        'status' => 'approved',
    ]);
    $part = IncomingPart::query()->create([
        'part_number' => 'BLT-M10-55',
        'name' => 'Flange bolt M10 x 55',
        'uom' => 'PCS',
        'is_active' => true,
    ]);

    Volt::test('admin.quality.purchase.order-form-page')
        ->assertSet('lines', fn ($lines) => count($lines) === 1)
        ->call('addLine')
        ->assertSet('lines', fn ($lines) => count($lines) === 2)
        ->call('removeLine', 0)
        ->assertSet('lines', fn ($lines) => count($lines) === 1)
        ->assertHasNoErrors()
        ->set('po_number', 'PO-FORM-1')
        ->set('vendor_id', (string) $vendor->id)
        ->set('po_date', now()->toDateString())
        ->set('lines.0.part_id', (string) $part->id)
        ->set('lines.0.ordered_qty', 1)
        ->set('lines.0.uom', 'PCS')
        ->set('lines.0.unit_price', 5)
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('admin::quality.purchase.orders'));

    $order = PurchaseOrder::query()->where('po_number', 'PO-FORM-1')->first();

    expect($order)->not->toBeNull()
        ->and($order->vendor_id)->toBe($vendor->id)
        ->and($order->lines)->toHaveCount(1)
        ->and((string) $order->lines->first()->part_id)->toBe((string) $part->id);
});

it('paginates purchase order lines on the form', function () {
    $this->actingAs(User::factory()->create([
        'user_type' => 'admin',
        'username' => 'po-lines-page-admin',
    ]));

    $component = Volt::test('admin.quality.purchase.order-form-page');

    foreach (range(1, 6) as $ignored) {
        $component->call('addLine');
    }

    $component
        ->assertSet('lines', fn ($lines) => count($lines) === 7)
        ->assertSet('linesPage', 2)
        ->call('gotoLinesPage', 1)
        ->assertSet('linesPage', 1)
        ->assertSee(__('Showing :from–:to of :total', ['from' => 1, 'to' => 5, 'total' => 7]));
});
