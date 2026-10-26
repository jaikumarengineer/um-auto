<?php

use App\Models\User;
use Livewire\Volt\Volt;
use Modules\Quality\Database\Seeders\QualityDatabaseSeeder;
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
        ->assertSee('Purchase order balance')
        ->assertSee('PO-2026-0142')
        ->assertDontSee('Standard vs report')
        ->assertDontSee('IR-2026-');

    foreach ([
        'admin::quality.incoming.vendors',
        'admin::quality.incoming.categories',
        'admin::quality.incoming.standards',
        'admin::quality.incoming.reports',
        'admin::users.list',
        'admin::settings.general',
        'admin::dashboard',
    ] as $name) {
        $this->get(route($name))->assertForbidden();
    }

    $this->actingAs($incoming);
    $this->get(route('admin::quality.incoming.vendors'))
        ->assertOk()
        ->assertDontSee('POs');
    $this->get(route('admin::quality.incoming.categories'))->assertOk();
    $this->get(route('admin::quality.incoming.reports.show', $report))->assertOk();
    $this->get(route('admin::quality.analysis'))
        ->assertSee('Standard vs report')
        ->assertSee('IR-2026-')
        ->assertDontSee('Purchase order balance')
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
        ->assertSee('IR-2026-');

    $this->actingAs($staff);
    $this->get(route('admin::dashboard'))
        ->assertOk()
        ->assertSeeInOrder(['Quality demo', 'Management', 'Server & Settings', 'Categories']);

    Volt::test('admin.auth.login')
        ->set('email', $purchase->email)
        ->set('password', 'password')
        ->call('login')
        ->assertRedirect(route('admin::quality.purchase.orders', absolute: false));

    Volt::test('admin.auth.login')
        ->set('email', $incoming->email)
        ->set('password', 'password')
        ->call('login')
        ->assertRedirect(route('admin::quality.incoming.vendors', absolute: false));
});
