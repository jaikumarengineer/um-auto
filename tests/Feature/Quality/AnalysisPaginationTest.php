<?php

use App\Models\User;
use Livewire\Volt\Volt;
use Modules\Quality\Database\Seeders\QualityDatabaseSeeder;
use Modules\Quality\Models\ApprovedVendor;
use Modules\Quality\Models\IncomingPart;
use Modules\Quality\Models\PurchaseOrder;
use Modules\Quality\Services\QualityAnalysis;

it('filters and paginates analysis tables independently with vendor code search', function () {
    $this->seed(QualityDatabaseSeeder::class);

    $this->actingAs(User::factory()->create([
        'user_type' => 'admin',
        'username' => 'analysis-pagination',
    ]));

    $vendor = ApprovedVendor::query()->where('code', 'V-1042')->firstOrFail();
    $other = ApprovedVendor::query()->where('code', 'V-1188')->firstOrFail();
    $part = IncomingPart::query()->where('part_number', 'BRK-PAD-220')->firstOrFail();

    PurchaseOrder::query()->create([
        'po_number' => 'PO-SEARCH-A',
        'vendor_id' => $vendor->id,
        'po_date' => now()->toDateString(),
        'status' => 'open',
    ])->lines()->create([
        'part_id' => $part->id,
        'line_no' => 1,
        'ordered_qty' => 10,
        'uom' => 'PCS',
        'unit_price' => 1,
    ]);

    PurchaseOrder::query()->create([
        'po_number' => 'PO-SEARCH-B',
        'vendor_id' => $other->id,
        'po_date' => now()->toDateString(),
        'status' => 'open',
    ])->lines()->create([
        'part_id' => $part->id,
        'line_no' => 1,
        'ordered_qty' => 5,
        'uom' => 'PCS',
        'unit_price' => 1,
    ]);

    $analysis = app(QualityAnalysis::class);

    $parts = $analysis->paginatedPartRows('BRK-PAD-220', 10, 'partsPage');
    expect($parts->total())->toBe(1)
        ->and($parts->items()[0]['part_number'])->toBe('BRK-PAD-220');

    expect($analysis->paginatedPartRows('NO-SUCH-PART', 10, 'partsPage')->total())->toBe(0);

    $ordered = collect($analysis->paginatedPartRows('', 50, 'partsPage', true, 'ordered', 'desc')->items())
        ->pluck('ordered')
        ->all();
    $sorted = $ordered;
    rsort($sorted);
    expect($ordered)->toBe($sorted);

    $balances = $analysis->paginatedBalances('V-1042', 'po', 'asc', 10, 'balancesPage');
    $poNumbers = collect($balances->items())->pluck('po')->all();

    expect($poNumbers)->toContain('PO-SEARCH-A')
        ->and($poNumbers)->not->toContain('PO-SEARCH-B');

    Volt::test('admin.quality.analysis-page')
        ->assertSee('Parts movement')
        ->set('partSearch', 'NO-SUCH-PART')
        ->assertSee('No part movement yet.')
        ->set('partSearch', '')
        ->call('sortParts', 'gap')
        ->assertSet('partSortBy', 'gap')
        ->assertSet('partSortDirection', 'asc')
        ->call('sortParts', 'gap')
        ->assertSet('partSortDirection', 'desc')
        ->set('graphDepartment', 'purchase')
        ->assertSee('Purchase order balance')
        ->assertSee('Search PO, PI, vendor name, or vendor code')
        ->set('balanceSearch', 'V-1042')
        ->assertSee('PO-SEARCH-A')
        ->assertDontSee('PO-SEARCH-B');
});
