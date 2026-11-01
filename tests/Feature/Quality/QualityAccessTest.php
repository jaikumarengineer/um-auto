<?php

use App\Models\User;
use Livewire\Volt\Volt;
use Modules\Quality\Database\Seeders\QualityDatabaseSeeder;
use Modules\Quality\Models\IncomingPart;
use Modules\Quality\Models\InspectionReport;
use Modules\Quality\Models\PurchaseOrder;

it('keeps each department inside its own screens', function () {
    $this->seed(QualityDatabaseSeeder::class);

    $purchase = User::factory()->create([
        'user_type' => 'purchase',
        'username' => 'purchase-demo',
    ]);
    $incoming = User::factory()->create([
        'user_type' => 'incoming',
        'username' => 'incoming-demo',
    ]);
    $master = User::factory()->create([
        'user_type' => 'admin',
        'username' => 'master-demo',
    ]);
    $staff = User::factory()->create([
        'user_type' => 'staff',
        'username' => 'staff-demo',
    ]);

    $order = PurchaseOrder::query()->where('po_number', 'PO-2026-0142')->firstOrFail();
    $report = InspectionReport::query()->firstOrFail();
    $part = IncomingPart::query()->where('part_number', 'BRK-PAD-220')->firstOrFail();

    $this->actingAs($purchase);

    foreach ([
        'admin::quality.purchase.orders',
        'admin::quality.purchase.orders.create',
        'admin::quality.purchase.invoices',
        'admin::quality.analysis',
        'admin::quality.alerts',
        'admin::profile',
    ] as $name) {
        $this->get(route($name))->assertOk();
    }

    $this->get(route('admin::quality.purchase.orders.format', $order))->assertOk();
    $this->get(route('admin::quality.analysis'))
        ->assertSee('Purchase Department')
        ->assertSee('Purchase order balance')
        ->assertSee('PO Vs PI')
        ->assertDontSee('Vendor Wise PO Vs PI')
        ->assertSee('Date range')
        ->assertSee('Current month')
        ->assertSee('MTD / YTD')
        ->assertDontSee('Vendor wise')
        ->assertDontSee('Weekly')
        ->assertSee('PO-2026-0142')
        ->assertDontSee('PO vs PI qty')
        ->assertDontSee('Open PO value')
        ->assertSee('History tree')
        ->assertDontSee('Received vs inspected')
        ->assertDontSee('Parts movement')
        ->assertDontSee('MTD (PO / Inspection)')
        ->assertDontSee('YTD (PO / Inspection)')
        ->assertDontSee('Parts coming vs inspected')
        ->assertDontSee('wire:model.live="graphDepartment"', false)
        ->assertDontSee('Standard vs report')
        ->assertDontSee('Vendor quality')
        ->assertDontSee('IR-2026-');

    foreach ([
        'admin::quality.incoming.vendors',
        'admin::quality.incoming.categories',
        'admin::quality.incoming.reports',
        'admin::users.list',
        'admin::settings.general',
        'admin::dashboard',
    ] as $name) {
        $this->get(route($name))->assertForbidden();
    }

    $this->get(route('admin::quality.incoming.parts.standard', $part))->assertForbidden();

    $this->actingAs($incoming);
    $this->get(route('admin::quality.incoming.vendors'))->assertForbidden();
    $this->get(route('admin::quality.incoming.categories'))->assertForbidden();
    $this->get(route('admin::quality.incoming.parts'))->assertOk();
    $this->get(route('admin::quality.incoming.parts.standard', $part))
        ->assertOk()
        ->assertSee('Inspection Standard - Parts');
    $this->get(route('admin::quality.incoming.reports.show', $report))->assertOk();
    $this->get(route('admin::quality.analysis'))
        ->assertSee('Incoming Department')
        ->assertSee('Parts coming vs inspected')
        ->assertSee('sortParts(\'part\')', false)
        ->assertSee('History tree')
        ->assertSee('IR-2026-')
        ->assertSee('target="_blank"', false)
        ->assertDontSee('Standard vs report')
        ->assertDontSee('Vendor quality')
        ->assertDontSee('wire:model.live="graphDepartment"', false)
        ->assertDontSee('Purchase order balance')
        ->assertDontSee('PO vs PI qty')
        ->assertDontSee('Open PO value')
        ->assertDontSee('Received vs inspected')
        ->assertSee('MTD (PO / Inspection)')
        ->assertSee('YTD (PO / Inspection)')
        ->assertSee('Date range')
        ->assertSee('Current month')
        ->assertSee('MTD / YTD')
        ->assertDontSee('PO-2026-');

    foreach ([
        'admin::quality.purchase.orders',
        'admin::quality.purchase.invoices',
        'admin::users.list',
        'admin::settings.general',
    ] as $name) {
        $this->get(route($name))->assertForbidden();
    }

    $this->actingAs($master);
    foreach ([
        'admin::dashboard',
        'admin::users.list',
        'admin::users.create',
        'admin::quality.purchase.orders',
        'admin::quality.incoming.vendors',
    ] as $name) {
        $this->get(route($name))->assertOk();
    }

    $this->get(route('admin::quality.incoming.vendors'))->assertSee('POs');
    $this->get(route('admin::quality.analysis'))
        ->assertSee('PO-2026-')
        ->assertSee('IR-2026-')
        ->assertSee('wire:model.live="graphDepartment"', false)
        ->assertSee('Department')
        ->assertSee('Incoming Department')
        ->assertSee('Parts coming vs inspected')
        ->assertSee('History tree')
        ->assertSee('Date range')
        ->assertSee('MTD / YTD')
        ->assertSee('Vendor')
        ->assertSee('target="_blank"', false)
        ->assertDontSee('Vendor quality')
        ->assertDontSee('Last 14 days')
        ->assertDontSee('Monthly trend')
        ->assertDontSee('Yearly trend')
        ->assertDontSee('Standard vs report');

    Volt::test('admin.quality.analysis-page')
        ->assertSet('graphDepartment', 'incoming')
        ->assertSee('Incoming Department')
        ->assertSee('Parts coming vs inspected')
        ->assertDontSee('PO Vs PI')
        ->assertSee('Parts movement')
        ->assertSee('MTD (PO / Inspection)')
        ->assertDontSee('Purchase order balance')
        ->assertDontSee('Open PO value')
        ->set('graphDepartment', 'purchase')
        ->assertSee('Purchase Department')
        ->assertSee('PO Vs PI')
        ->assertSee('Purchase order balance')
        ->assertDontSee('Open PO value')
        ->assertSee('History tree')
        ->assertDontSee('Parts movement')
        ->assertDontSee('Received vs inspected')
        ->assertDontSee('MTD (PO / Inspection)')
        ->assertDontSee('Vendor Wise')
        ->assertSee('Month wise')
        ->assertSee('Yearly')
        ->assertDontSee('Parts coming vs inspected')
        ->set('graphDepartment', 'not-a-department')
        ->assertSet('graphDepartment', 'incoming');

    $this->actingAs($staff);
    $this->get(route('admin::dashboard'))
        ->assertOk()
        ->assertSee('Management')
        ->assertSee('SPQol MES')
        ->assertDontSee('Server & Settings');
    $this->get(route('admin::settings.general'))->assertForbidden();
    $this->get(route('admin::quality.incoming.vendors'))->assertOk();

    $manager = User::factory()->create([
        'user_type' => 'manager',
        'username' => 'manager-demo',
    ]);
    $this->actingAs($manager);
    $this->get(route('admin::dashboard'))->assertOk();
    $this->get(route('admin::quality.incoming.vendors'))->assertOk();
    $this->get(route('admin::quality.incoming.categories'))->assertOk();
    $this->get(route('admin::settings.general'))->assertForbidden();

    Volt::test('admin.auth.login')
        ->set('username', $purchase->username)
        ->set('password', 'password')
        ->call('login')
        ->assertRedirect(route('admin::quality.purchase.orders', absolute: false));

    Volt::test('admin.auth.login')
        ->set('username', $incoming->username)
        ->set('password', 'password')
        ->call('login')
        ->assertRedirect(route('admin::quality.incoming.parts', absolute: false));
});
