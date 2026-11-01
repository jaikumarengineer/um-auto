<?php

use App\Models\Role;
use App\Models\User;
use App\Services\RecordApproval;
use App\Support\PermissionCatalog;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Volt\Volt;
use Modules\Quality\Models\ApprovedVendor;
use Modules\Quality\Models\IncomingPart;
use Modules\Quality\Models\PurchaseOrder;
use Modules\Quality\Services\QualityWorkflow;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('allows admins to open roles pages and blocks department users', function () {
    $admin = User::factory()->create([
        'user_type' => 'admin',
        'username' => 'roles-admin',
    ]);
    $purchase = User::factory()->create([
        'user_type' => 'purchase',
        'username' => 'roles-purchase',
    ]);

    $this->actingAs($admin)
        ->get(route('admin::users.roles.index'))
        ->assertOk();

    $this->actingAs($admin)
        ->get(route('admin::users.roles.create'))
        ->assertOk();

    $this->actingAs($purchase)
        ->get(route('admin::users.roles.index'))
        ->assertForbidden();
});

it('applies a ticked role permission to incoming, purchase, and manager users', function () {
    $admin = User::factory()->create([
        'user_type' => 'admin',
        'username' => 'roles-apply-admin',
    ]);
    $incoming = User::factory()->create([
        'user_type' => 'incoming',
        'username' => 'roles-apply-incoming',
    ]);
    $purchase = User::factory()->create([
        'user_type' => 'purchase',
        'username' => 'roles-apply-purchase',
    ]);
    $manager = User::factory()->create([
        'user_type' => 'manager',
        'username' => 'roles-apply-manager',
    ]);

    $sampling = PermissionCatalog::name('quality.incoming.sampling', 'edit');
    $incoming->syncPermissions(
        collect(PermissionCatalog::permissionsForRole('incoming'))
            ->reject(fn (string $name) => str_contains($name, 'quality.incoming.sampling.'))
            ->values()
            ->all()
    );

    expect($incoming->fresh()->canModule('quality.incoming.sampling', 'edit'))->toBeTrue();

    $this->actingAs($admin);

    Volt::test('admin.users.roles-add-edit-page', ['role' => Role::findByName('incoming', 'web')])
        ->set('grantedPermissions', [$sampling])
        ->call('save')
        ->assertHasNoErrors();

    expect($incoming->fresh()->canModule('quality.incoming.sampling', 'edit'))->toBeTrue()
        ->and($incoming->fresh()->canModule('quality.incoming.reports'))->toBeFalse()
        ->and($purchase->fresh()->canModule('quality.purchase.orders'))->toBeTrue()
        ->and($manager->fresh()->canModule('quality.incoming.sampling', 'edit'))->toBeTrue();

    Volt::test('admin.users.users-add-edit-page', ['user_id' => $incoming->id])
        ->call('openPermissionsFlyout')
        ->call('toggleDraftPermission', $sampling)
        ->assertSet('draftPermissions', [])
        ->call('toggleDraftPermission', PermissionCatalog::name('quality.incoming.reports', 'view'))
        ->assertSet('draftPermissions', [PermissionCatalog::name('quality.incoming.reports', 'view')]);
});

it('saves editable role permissions from the roles form', function () {
    $admin = User::factory()->create([
        'user_type' => 'admin',
        'username' => 'roles-editor',
    ]);

    $role = Role::findByName('purchase', 'web');
    $permission = PermissionCatalog::name('quality.alerts', 'view');

    $this->actingAs($admin);

    Volt::test('admin.users.roles-add-edit-page', ['role' => $role])
        ->set('description', 'Purchase team template')
        ->set('is_active', true)
        ->set('grantedPermissions', [$permission])
        ->call('save')
        ->assertHasNoErrors();

    expect($role->fresh()->description)->toBe('Purchase team template')
        ->and($role->fresh()->permissions->pluck('name')->all())->toBe([$permission]);
});

it('copies permissions from another role on the user form', function () {
    $admin = User::factory()->create([
        'user_type' => 'admin',
        'username' => 'copy-admin',
    ]);

    $managerPerms = PermissionCatalog::permissionsForRole('manager');

    $this->actingAs($admin);

    Volt::test('admin.users.users-add-edit-page')
        ->set('name', 'Copy User')
        ->set('username', 'copy-user')
        ->set('email', 'copy-user@example.com')
        ->set('password', 'Password1!')
        ->set('password_confirmation', 'Password1!')
        ->set('user_type', 'purchase')
        ->set('copyFromRole', 'manager')
        ->assertSet('grantedPermissions', $managerPerms);
});

it('customizes user permissions in the right flyout without applying until save', function () {
    $admin = User::factory()->create([
        'user_type' => 'admin',
        'username' => 'flyout-admin',
    ]);

    $purchasePerm = PermissionCatalog::name('quality.purchase.orders', 'view');
    $before = PermissionCatalog::permissionsForRole('purchase');

    $this->actingAs($admin);

    Volt::test('admin.users.users-add-edit-page')
        ->set('user_type', 'purchase')
        ->assertSet('grantedPermissions', $before)
        ->call('openPermissionsFlyout')
        ->assertSet('showPermissionsFlyout', true)
        ->assertSet('draftPermissions', $before)
        ->call('clearDraftPermissions')
        ->set('draftPermissions', [$purchasePerm])
        ->assertSet('grantedPermissions', $before)
        ->call('savePermissionsFlyout')
        ->assertSet('showPermissionsFlyout', false)
        ->assertSet('grantedPermissions', [$purchasePerm]);
});

it('updates accepted status and blocks booking until approved', function () {
    $purchase = User::factory()->create([
        'user_type' => 'purchase',
        'username' => 'status-purchase',
    ]);
    $manager = User::factory()->create([
        'user_type' => 'manager',
        'username' => 'status-manager',
    ]);

    $vendor = ApprovedVendor::query()->create([
        'code' => 'V-STAT',
        'name' => 'Status Vendor',
        'status' => 'approved',
    ]);
    $part = IncomingPart::query()->create([
        'part_number' => 'STAT-PART',
        'name' => 'Status Part',
        'uom' => 'PCS',
    ]);

    $this->actingAs($purchase);

    $order = app(QualityWorkflow::class)->savePurchaseOrder([
        'po_number' => 'PO-STAT-1',
        'vendor_id' => $vendor->id,
        'po_date' => now()->toDateString(),
        'expected_date' => null,
        'currency' => 'INR',
        'remarks' => null,
        'status' => 'open',
    ], [[
        'part_id' => $part->id,
        'ordered_qty' => 3,
        'uom' => 'PCS',
        'unit_price' => 12,
    ]]);

    expect($order->approval_status)->toBe(RecordApproval::PENDING);

    $this->actingAs($manager);

    Volt::test('admin.quality.purchase.orders-page')
        ->assertSeeHtml('data-flux-avatar')
        ->call('openAcceptedStatus', $order->id)
        ->set('acceptedStatus', RecordApproval::UNDER_REVIEW)
        ->set('acceptedStatusReason', 'Checking vendor docs')
        ->call('saveAcceptedStatus')
        ->assertHasNoErrors();

    $order = $order->fresh();
    expect($order->approval_status)->toBe(RecordApproval::UNDER_REVIEW);

    expect(fn () => app(QualityWorkflow::class)->savePurchaseInvoice([
        'invoice_number' => 'PI-STAT-1',
        'purchase_order_id' => $order->id,
        'invoice_date' => now()->toDateString(),
        'remarks' => null,
    ], [[
        'purchase_order_line_id' => $order->lines()->first()->id,
        'invoiced_qty' => 3,
        'lots' => [['lot_number' => 'LOT-STAT-1', 'qty' => 3]],
    ]]))->toThrow(Illuminate\Validation\ValidationException::class);

    app(QualityWorkflow::class)->updatePurchaseOrderAcceptedStatus($order, RecordApproval::APPROVED);

    expect(PurchaseOrder::query()->whereKey($order->id)->value('approval_status'))
        ->toBe(RecordApproval::APPROVED);
});
