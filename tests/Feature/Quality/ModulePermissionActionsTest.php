<?php

use App\Models\Role;
use App\Models\User;
use App\Support\PermissionCatalog;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Volt\Volt;
use Modules\Quality\Models\ApprovedVendor;
use Modules\Quality\Models\IncomingPart;
use Modules\Quality\Models\PurchaseOrder;
use Modules\Quality\Services\QualityWorkflow;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('hides edit and delete order actions when those permissions are missing', function () {
    $user = User::factory()->create([
        'user_type' => 'purchase',
        'username' => 'po-limited',
    ]);

    $allowed = [
        PermissionCatalog::name('quality.purchase.orders', 'access'),
        PermissionCatalog::name('quality.purchase.orders', 'view'),
        PermissionCatalog::name('quality.purchase.orders', 'add'),
    ];

    foreach ($allowed as $name) {
        Permission::findOrCreate($name, 'web');
    }

    Role::findByName('purchase', 'web')->syncPermissions($allowed);
    $user->syncPermissions($allowed);

    $vendor = ApprovedVendor::query()->create([
        'code' => 'V-PERM',
        'name' => 'Perm Vendor',
        'status' => 'approved',
    ]);
    $part = IncomingPart::query()->create([
        'part_number' => 'PERM-PART',
        'name' => 'Perm Part',
        'uom' => 'PCS',
    ]);

    $manager = User::factory()->create([
        'user_type' => 'manager',
        'username' => 'po-manager-seed',
    ]);
    $this->actingAs($manager);

    $order = app(QualityWorkflow::class)->savePurchaseOrder([
        'po_number' => 'PO-PERM-1',
        'vendor_id' => $vendor->id,
        'po_date' => now()->toDateString(),
        'expected_date' => null,
        'currency' => 'INR',
        'remarks' => null,
        'status' => 'open',
    ], [[
        'part_id' => $part->id,
        'ordered_qty' => 1,
        'uom' => 'PCS',
        'unit_price' => 5,
    ]]);

    $this->actingAs($user);

    $this->get(route('admin::quality.purchase.orders'))->assertOk();
    $this->get(route('admin::quality.purchase.orders.edit', $order))->assertForbidden();

    expect(module_can('quality.purchase.orders', 'view'))->toBeTrue()
        ->and(module_can('quality.purchase.orders', 'add'))->toBeTrue()
        ->and(module_can('quality.purchase.orders', 'edit'))->toBeFalse()
        ->and(module_can('quality.purchase.orders', 'delete'))->toBeFalse();

    Volt::test('admin.quality.purchase.orders-page')
        ->assertSee(__('View'))
        ->assertSee(__('Format'))
        ->assertDontSeeHtml('wire:navigate">'.e(__('Edit')))
        ->assertDontSee(__('Delete'));

    expect(PurchaseOrder::query()->whereKey($order->id)->exists())->toBeTrue();
});

it('forbids creating purchase orders without the add permission', function () {
    $user = User::factory()->create([
        'user_type' => 'purchase',
        'username' => 'po-view-only',
    ]);

    $perms = [
        PermissionCatalog::name('quality.purchase.orders', 'access'),
        PermissionCatalog::name('quality.purchase.orders', 'view'),
    ];
    foreach ($perms as $name) {
        Permission::findOrCreate($name, 'web');
    }
    Role::findByName('purchase', 'web')->syncPermissions($perms);
    $user->syncPermissions($perms);

    $this->actingAs($user)
        ->get(route('admin::quality.purchase.orders.create'))
        ->assertForbidden();
});
