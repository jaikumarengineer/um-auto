<?php

use App\Models\User;
use Modules\Quality\Database\Seeders\QualityDatabaseSeeder;
use Modules\Quality\Models\PurchaseInvoice;
use Modules\Quality\Models\PurchaseOrder;

it('renders the quality demo pages', function () {
    $this->seed(QualityDatabaseSeeder::class);

    $this->actingAs(User::factory()->create([
        'user_type' => 'admin',
        'username' => 'quality-admin',
    ]));

    foreach ([
        'admin::quality.analysis',
        'admin::quality.alerts',
        'admin::quality.purchase.orders',
        'admin::quality.purchase.orders.create',
        'admin::quality.purchase.invoices',
        'admin::quality.purchase.invoices.create',
        'admin::quality.incoming.vendors',
        'admin::quality.incoming.parts',
        'admin::quality.incoming.categories',
        'admin::quality.incoming.sampling',
        'admin::quality.incoming.standards',
        'admin::quality.incoming.standards.create',
        'admin::quality.incoming.reports',
        'admin::quality.incoming.reports.create',
    ] as $name) {
        $this->get(route($name))->assertOk();
    }

    $order = PurchaseOrder::query()->where('po_number', 'PO-2026-0142')->firstOrFail();
    $invoice = PurchaseInvoice::query()->where('invoice_number', 'PI-8821')->firstOrFail();

    $this->get(route('admin::quality.purchase.orders.edit', $order))->assertOk();
    $this->get(route('admin::quality.purchase.orders.format', $order))->assertOk();
    $this->get(route('admin::quality.purchase.invoices.edit', $invoice))->assertOk();
    $this->get(route('admin::quality.purchase.invoices.format', $invoice))->assertOk();
    $this->get(route('admin::quality.incoming.reports.show', $invoice->lots()->whereHas('reports')->first()->reports()->first()))->assertOk();
});
