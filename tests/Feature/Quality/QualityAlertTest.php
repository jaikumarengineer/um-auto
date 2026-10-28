<?php

use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Livewire\Volt\Volt;
use Modules\Quality\Models\ApprovedVendor;
use Modules\Quality\Models\IncomingPart;
use Modules\Quality\Models\PurchaseOrder;
use Modules\Quality\Models\QualityAlert;
use Modules\Quality\Services\QualityDigest;
use Modules\Quality\Support\QualityAlertCatalog;

it('matches a user to one department, status, report, and summary', function () {
    $prefs = [
        'enabled' => true,
        'departments' => ['purchase'],
        'statuses' => ['overdue', 'completed'],
        'reports' => ['po_vs_pi'],
        'summaries' => ['daily', 'monthly'],
    ];

    expect(QualityAlertCatalog::matches('po_vs_pi.overdue', $prefs))->toBeTrue()
        ->and(QualityAlertCatalog::matches('po_vs_pi.completed', $prefs))->toBeTrue()
        ->and(QualityAlertCatalog::matches('po_vs_pi.pending', $prefs))->toBeFalse()
        ->and(QualityAlertCatalog::matches('incoming_inspection.overdue', $prefs))->toBeFalse()
        ->and(QualityAlertCatalog::matches('po_vs_pi.summary.daily', $prefs))->toBeTrue()
        ->and(QualityAlertCatalog::matches('po_vs_pi.summary.weekly', $prefs))->toBeFalse()
        ->and(QualityAlertCatalog::matches('incoming_inspection.summary.monthly', $prefs))->toBeFalse()
        ->and(QualityAlertCatalog::matches('po_vs_pi.overdue', ['enabled' => false]))->toBeFalse();
});

it('keeps an empty checkbox group from receiving that mail', function () {
    expect(QualityAlertCatalog::toggle(['all'], 'all', ['purchase', 'incoming']))->toBe([])
        ->and(QualityAlertCatalog::toggle(['all'], 'purchase', ['purchase', 'incoming']))->toBe(['incoming'])
        ->and(QualityAlertCatalog::toggle(['purchase'], 'incoming', ['purchase', 'incoming']))->toBe(['all'])
        ->and(QualityAlertCatalog::expandForUi(['all'], ['purchase', 'incoming']))->toBe(['purchase', 'incoming'])
        ->and(QualityAlertCatalog::collapseFromUi(['purchase', 'incoming'], ['purchase', 'incoming']))->toBe(['all'])
        ->and(QualityAlertCatalog::collapseFromUi(['purchase'], ['purchase', 'incoming']))->toBe(['purchase']);

    $cleared = QualityAlertCatalog::normalize([
        'enabled' => true,
        'departments' => [],
        'statuses' => ['overdue'],
        'reports' => ['po_vs_pi'],
        'summaries' => ['daily'],
    ]);

    expect(QualityAlertCatalog::matches('po_vs_pi.overdue', $cleared))->toBeFalse();
});

it('emails overdue PO vs PI only to the subscribed user', function () {
    setting([
        'enabled' => true,
        'fallback_email' => '',
    ], 'email_alerts');

    $purchase = User::factory()->create([
        'user_type' => 'purchase',
        'email' => 'purchase-alerts@example.com',
        'is_active' => true,
        'email_alert_preferences' => [
            'enabled' => true,
            'departments' => ['purchase'],
            'statuses' => ['overdue'],
            'reports' => ['po_vs_pi'],
            'summaries' => ['daily'],
        ],
    ]);

    User::factory()->create([
        'user_type' => 'incoming',
        'email' => 'incoming-alerts@example.com',
        'is_active' => true,
        'email_alert_preferences' => [
            'enabled' => true,
            'departments' => ['incoming'],
            'statuses' => ['all'],
            'reports' => ['incoming_inspection'],
            'summaries' => [],
        ],
    ]);

    $vendor = ApprovedVendor::query()->create([
        'code' => 'V-ALERT',
        'name' => 'Apex Castings',
        'status' => 'approved',
    ]);
    $part = IncomingPart::query()->create([
        'part_number' => 'BRK-ALERT',
        'name' => 'Pad',
        'uom' => 'PCS',
    ]);
    $order = PurchaseOrder::query()->create([
        'po_number' => 'PO-ALERT-1',
        'vendor_id' => $vendor->id,
        'po_date' => now()->subDays(10)->toDateString(),
        'expected_date' => now()->subDay()->toDateString(),
        'status' => 'open',
        'currency' => 'INR',
    ]);
    $order->lines()->create([
        'part_id' => $part->id,
        'line_no' => 1,
        'ordered_qty' => 12,
        'uom' => 'PCS',
        'unit_price' => 10,
    ]);

    Mail::fake();

    $alerts = app(QualityDigest::class)->generate(now(), sendMail: true, cadence: 'daily');

    $purchaseMail = collect($alerts)->where('channel', 'email')->where('recipient', $purchase->email);
    $incomingMail = collect($alerts)->where('channel', 'email')->where('recipient', 'incoming-alerts@example.com');

    expect($purchaseMail->pluck('alert_type')->all())->toEqualCanonicalizing([
        'po_vs_pi.overdue',
        'po_vs_pi.summary.daily',
    ])->and($purchaseMail->firstWhere('alert_type', 'po_vs_pi.overdue')->body)->toContain('PO-ALERT-1')
        ->and($purchaseMail->firstWhere('alert_type', 'po_vs_pi.overdue')->status)->toBe('sent')
        ->and($incomingMail->pluck('alert_type')->all())->not->toContain('po_vs_pi.overdue')
        ->and($incomingMail->pluck('alert_type')->all())->toContain('incoming_inspection.pending');

    $weekly = app(QualityDigest::class)->generate(now(), sendMail: false, cadence: 'weekly');

    expect(collect($weekly)->where('channel', 'email'))->toHaveCount(0)
        ->and(collect($weekly)->where('channel', 'whatsapp')->pluck('alert_type')->unique()->sort()->values()->all())
        ->toBe(['incoming_inspection.summary.weekly', 'po_vs_pi.summary.weekly']);
});

it('sends nothing while email alerts are switched off', function () {
    setting(['enabled' => false], 'email_alerts');

    User::factory()->create([
        'email' => 'quiet@example.com',
        'is_active' => true,
        'email_alert_preferences' => ['enabled' => true],
    ]);

    expect(app(QualityDigest::class)->generate(now(), sendMail: false))->toBe([]);
});

it('lets a master admin set the email alert switches', function () {
    $admin = User::factory()->create([
        'user_type' => 'admin',
        'username' => 'alert-admin',
    ]);

    $this->actingAs($admin);

    $this->get(route('admin::settings.email-alerts'))
        ->assertOk()
        ->assertSee('PO vs PI')
        ->assertSee('Incoming inspection reports')
        ->assertSee('Monthwise')
        ->assertSee('Overdue');

    $this->get(route('admin::users.create'))
        ->assertOk()
        ->assertSee('Email alerts')
        ->assertSee('Send quality email alerts to this user');

    $page = Volt::test('admin.settings.email-alerts-page');

    $page->assertSet('departmentsAll', true)
        ->assertSet('departmentChoices', ['purchase', 'incoming'])
        ->set('departmentsAll', false)
        ->assertSet('departmentChoices', [])
        ->set('statusChoices', ['overdue'])
        ->assertSet('statusesAll', false)
        ->set('statusChoices', ['overdue', 'pending', 'completed'])
        ->assertSet('statusesAll', true)
        ->set('enabled', true)
        ->set('departmentChoices', ['incoming'])
        ->set('departmentsAll', false)
        ->set('statusChoices', ['pending', 'completed'])
        ->set('statusesAll', false)
        ->set('reportChoices', ['incoming_inspection'])
        ->set('reportsAll', false)
        ->set('summaryChoices', ['weekly'])
        ->set('summariesAll', false)
        ->set('send_time', '07:30')
        ->set('overdue_days', 4)
        ->set('fallback_email', 'desk@example.com')
        ->call('save')
        ->assertHasNoErrors();

    $saved = QualityAlertCatalog::systemPreferences();

    expect($saved['enabled'])->toBeTrue()
        ->and($saved['departments'])->toBe(['incoming'])
        ->and($saved['statuses'])->toBe(['pending', 'completed'])
        ->and($saved['reports'])->toBe(['incoming_inspection'])
        ->and($saved['summaries'])->toBe(['weekly'])
        ->and($saved['send_time'])->toBe('07:30')
        ->and($saved['overdue_days'])->toBe(4)
        ->and($saved['fallback_email'])->toBe('desk@example.com');

    expect(QualityAlert::query()->count())->toBe(0);
});

it('keeps department users out of email alert settings', function () {
    $purchase = User::factory()->create([
        'user_type' => 'purchase',
        'username' => 'purchase-no-settings',
    ]);

    $this->actingAs($purchase)
        ->get(route('admin::settings.email-alerts'))
        ->assertForbidden();
});

it('lets a master admin set email alerts on a user', function () {
    $admin = User::factory()->create([
        'user_type' => 'admin',
        'username' => 'user-alert-admin',
    ]);
    $target = User::factory()->create([
        'user_type' => 'purchase',
        'username' => 'purchase-alert-target',
        'email_alert_preferences' => null,
    ]);

    $this->actingAs($admin);

    Volt::test('admin.users.users-add-edit-page', ['user_id' => $target->id])
        ->assertSet('email_alerts_enabled', false)
        ->set('email_alerts_enabled', true)
        ->assertSet('departmentsAll', true)
        ->assertSet('departmentChoices', ['purchase', 'incoming'])
        ->set('departmentsAll', false)
        ->assertSet('departmentChoices', [])
        ->set('departmentChoices', ['purchase'])
        ->assertSet('departmentsAll', false)
        ->set('statusChoices', ['overdue', 'pending'])
        ->set('statusesAll', false)
        ->set('reportChoices', ['po_vs_pi'])
        ->set('reportsAll', false)
        ->set('summaryChoices', ['daily'])
        ->set('summariesAll', false)
        ->set('password', '')
        ->call('save')
        ->assertHasNoErrors();

    $target->refresh();

    expect($target->emailAlertPreferences())->toMatchArray([
        'enabled' => true,
        'departments' => ['purchase'],
        'statuses' => ['overdue', 'pending'],
        'reports' => ['po_vs_pi'],
        'summaries' => ['daily'],
    ]);
});
