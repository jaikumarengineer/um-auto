<?php

use App\Models\User;
use Livewire\Volt\Volt;
use Modules\Quality\Models\ApprovedVendor;
use Modules\Quality\Models\PurchaseOrder;

it('leaves the vendor code blank until it is entered, and rejects a bad or duplicate code', function () {
    $this->actingAs(User::factory()->create([
        'user_type' => 'incoming',
        'username' => 'incoming-vendor-codes',
    ]));

    ApprovedVendor::query()->create([
        'code' => 'V-1310',
        'name' => 'Bright Polymers',
        'status' => 'pending',
    ]);

    Volt::test('admin.quality.incoming.vendors-page')
        ->call('createVendor')
        ->assertSet('code', '')
        ->set('name', 'River Metals')
        ->call('saveVendor')
        ->assertHasErrors(['code' => 'required']);

    expect(ApprovedVendor::query()->where('name', 'River Metals')->exists())->toBeFalse();

    Volt::test('admin.quality.incoming.vendors-page')
        ->call('createVendor')
        ->set('code', 'v-1311')
        ->set('name', 'River Metals')
        ->call('saveVendor')
        ->assertHasNoErrors();

    expect(ApprovedVendor::query()->where('name', 'River Metals')->value('code'))->toBe('V-1311');

    Volt::test('admin.quality.incoming.vendors-page')
        ->call('createVendor')
        ->set('code', 'not-a-code')
        ->set('name', 'Bad Code Metals')
        ->call('saveVendor')
        ->assertHasErrors(['code' => 'regex']);

    Volt::test('admin.quality.incoming.vendors-page')
        ->call('createVendor')
        ->set('code', 'V-1310')
        ->set('name', 'Duplicate Metals')
        ->call('saveVendor')
        ->assertHasErrors(['code' => 'unique']);

    expect(ApprovedVendor::query()->where('name', 'Duplicate Metals')->exists())->toBeFalse();
});

it('shows the vendor code after the name and finds a purchase order by that code', function () {
    $this->actingAs(User::factory()->create([
        'user_type' => 'admin',
        'username' => 'vendor-display-admin',
    ]));

    $match = ApprovedVendor::query()->create([
        'code' => 'V-2042',
        'name' => 'River Metals',
        'status' => 'approved',
    ]);
    $other = ApprovedVendor::query()->create([
        'code' => 'V-3099',
        'name' => 'Other Metals',
        'status' => 'approved',
    ]);

    PurchaseOrder::query()->create([
        'po_number' => 'PO-MATCH',
        'vendor_id' => $match->id,
        'po_date' => now()->toDateString(),
        'status' => 'open',
    ]);
    PurchaseOrder::query()->create([
        'po_number' => 'PO-OTHER',
        'vendor_id' => $other->id,
        'po_date' => now()->toDateString(),
        'status' => 'open',
    ]);

    expect(Volt::test('admin.quality.purchase.order-form-page')->html())
        ->toContain('River Metals \\\\u00b7 V-2042')
        ->not->toContain('V-2042 \\\\u00b7 River Metals');

    expect(Volt::test('admin.quality.incoming.parts-page')->call('createPart')->html())
        ->toContain('River Metals \\\\u00b7 V-2042');

    expect(Volt::test('admin.quality.purchase.invoice-form-page')->html())
        ->toContain('PO-MATCH \\\\u00b7 River Metals \\\\u00b7 V-2042');

    Volt::test('admin.quality.purchase.orders-page')
        ->set('search', 'V-2042')
        ->assertSee('PO-MATCH')
        ->assertDontSee('PO-OTHER')
        ->assertSee('Search PO number, vendor name, or vendor code');

    Volt::test('admin.quality.incoming.vendors-page')
        ->call('createVendor')
        ->assertSet('code', '')
        ->assertSee('Enter a code like V-1001.')
        ->assertDontSee('Assigned automatically');
});
