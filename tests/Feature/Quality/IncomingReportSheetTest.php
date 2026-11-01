<?php

use App\Models\User;
use Livewire\Volt\Volt;
use Modules\Quality\Database\Seeders\QualityDatabaseSeeder;
use Modules\Quality\Models\IncomingLot;
use Modules\Quality\Models\InspectionReport;
use Modules\Quality\Support\Ml08SampleQuote;
use Modules\Quality\Support\Qty;

it('locks the sample size from the lot band and scores each sample against the standard', function () {
    $this->seed(QualityDatabaseSeeder::class);

    $thousand = Ml08SampleQuote::forClasses(1000, ['C', 'M', 'O', 'Mi']);
    $star = Ml08SampleQuote::forClasses(5, ['C']);
    $capped = Ml08SampleQuote::forClasses(10, ['C']);

    expect($thousand['C']['sample_size'])->toBe(34)
        ->and($thousand['M']['sample_size'])->toBe(19)
        ->and($thousand['O']['sample_size'])->toBe(11)
        ->and($thousand['Mi']['sample_size'])->toBe(11)
        ->and($star['C']['sample_size'])->toBe(5)
        ->and($star['C']['entire_lot'])->toBeTrue()
        ->and($star['C']['sampling_rate'])->toBe(1.0)
        ->and($capped['C']['sample_size'])->toBe(10)
        ->and($capped['C']['entire_lot'])->toBeFalse();

    $this->actingAs(User::factory()->create([
        'user_type' => 'incoming',
        'username' => 'incoming-report',
        'name' => 'Jai',
        'last_name' => 'Kumar',
    ]));

    $lot = IncomingLot::query()->where('lot_number', 'L-BRK-150B')->firstOrFail();
    $invoiceId = (string) $lot->invoiceLine->purchase_invoice_id;

    Volt::test('admin.quality.incoming.report-form-page')
        ->call('selectPart', $lot->part_id)
        ->set('vendor_id', (string) $lot->vendor_id)
        ->set('invoice_id', $invoiceId)
        ->set('lot_id', (string) $lot->id)
        ->assertSet('sample_size', '13')
        ->assertSet('checks.0.class', 'C')
        ->assertCount('checks.0.umac', 13)
        ->assertSet('checks.1.class', 'M')
        ->assertCount('checks.1.umac', 11)
        ->assertSet('checks.2.class', 'O')
        ->assertCount('checks.2.umac', 6)
        ->assertSet('checks.3.class', 'Mi')
        ->assertCount('checks.3.umac', 6)
        ->assertSee('8.67%')
        ->assertSee('Major AQL 2.5')
        ->assertSee('Are you sure?')
        ->assertSee('Save this inspection report?')
        ->assertSee('Yes, save it')
        ->assertSet('checks.0.name', 'Thickness')
        ->assertSet('checks.0.method', 'Variable')
        ->assertSet('supplier_code', $lot->vendor->code)
        ->assertCount('checks.0.umac', 13)
        ->assertSeeHtml('id="sample-size" value="13" disabled readonly')
        ->assertSeeHtml('id="bill-no"')
        ->assertDontSee('UMAC MANESAR')
        ->assertSeeHtml('wire:model="assembly_name"')
        ->assertSeeHtml('type="number"')
        ->assertSeeHtml('type="text"')
        ->assertSet('checks.0.measurable', true)
        ->assertSet('checks.0.min', 11.7)
        ->assertSet('checks.0.max', 12.3)
        ->assertSet('checks.2.measurable', false)
        ->assertSeeHtml('wire:model.live="checks.2.supplier_status"')
        ->set('checks.0.umac.0', '13')
        ->assertSet('umac_decision', 'non_conforming')
        ->assertSee('Not OK')
        ->assertSeeHtml('iir-ng')
        ->assertSeeHtml('background:#dc2626!important')
        ->set('checks.0.umac.1', '12.0')
        ->assertSeeHtml('iir-ok')
        ->assertSeeHtml('background:#16a34a!important')
        ->set('checks.2.supplier.0', 'crack')
        ->set('checks.2.supplier_status', 'ng')
        ->set('assembly_name', 'Front axle')
        ->set('process', 'Incoming')
        ->set('accepted_qty', '140')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect();

    $report = InspectionReport::query()->where('incoming_lot_id', $lot->id)->latest('id')->firstOrFail();
    $number = $report->report_number;
    $incomingId = auth()->id();
    $thickness = $report->results()->where('characteristic_name', 'Thickness')->firstOrFail();

    $critical = $report->samples->firstWhere('characteristic_class', 'C');
    $major = $report->samples->firstWhere('characteristic_class', 'M');
    $others = $report->samples->firstWhere('characteristic_class', 'O');

    expect($report->sample_size)->toBe(13)
        ->and($critical->sample_size)->toBe(13)
        ->and($major->sample_size)->toBe(11)
        ->and($others->sample_size)->toBe(6)
        ->and((float) $critical->aql)->toBe(1.0)
        ->and((float) $major->aql)->toBe(2.5)
        ->and((float) $others->aql)->toBe(6.5)
        ->and($critical->umac_defectives)->toBe(1)
        ->and($critical->umac_result)->toBe('reject')
        ->and((float) $critical->umac_nonconforming_pct)->toBe(7.69)
        ->and($others->supplier_defectives)->toBe(1)
        ->and($others->supplier_result)->toBe('reject')
        ->and($critical->accept_number)->toBe(0)
        ->and($report->samplingPlan->code)->toBe('ML08-1-91-150')
        ->and((float) $report->accepted_qty)->toBe(140.0)
        ->and((float) $report->rejected_qty)->toBe(10.0)
        ->and($report->result)->toBe('fail')
        ->and($report->umac_status)->toBe('ng')
        ->and($report->assembly_name)->toBe('Front axle')
        ->and($report->inspector->full_name)->toBe('Jai Kumar')
        ->and($thickness->umac_readings[0])->toBe('13')
        ->and($thickness->umac_readings[1])->toBe('12.0')
        ->and($thickness->umac_result)->toBe('ng')
        ->and($thickness->method)->toBe('Variable')
        ->and($report->results()->where('characteristic_name', 'Friction face')->firstOrFail()->supplier_result)->toBe('ng');

    $this->get(route('admin::quality.incoming.reports.show', $report))
        ->assertOk()
        ->assertSee('INCOMING INSPECTION REPORT')
        ->assertSee('SUPPLIER OBSERVATION')
        ->assertSee('Not OK')
        ->assertSee('8.67%')
        ->assertSee('7.69%')
        ->assertSee('Reject')
        ->assertSee('Jai Kumar')
        ->assertSee(route('admin::quality.incoming.reports.edit', $report), false);

    $this->get(route('admin::quality.incoming.reports.edit', $report))->assertOk();
    $this->get(route('admin::quality.incoming.reports.pdf', $report))->assertOk();

    $excel = $this->get(route('admin::quality.incoming.reports.excel', $report));
    $excel->assertOk();
    expect($excel->headers->get('content-type'))->toContain('spreadsheet');

    $sheetFile = tempnam(sys_get_temp_dir(), 'report-xlsx');
    file_put_contents($sheetFile, $excel->streamedContent());
    $zip = new ZipArchive;
    expect($zip->open($sheetFile))->toBeTrue();
    $styles = $zip->getFromName('xl/styles.xml');
    $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();
    @unlink($sheetFile);

    expect($styles)->not->toContain('FFFF00')
        ->and($styles)->toContain('FFF4F4F5')
        ->and($sheet)->toContain('INCOMING INSPECTION REPORT')
        ->and($sheet)->toContain($number)
        ->and($sheet)->toContain('Front axle')
        ->and($sheet)->toContain('8.67%')
        ->and($sheet)->toContain('Critical')
        ->and($sheet)->toContain('A1:J1')
        ->and($sheet)->toContain('C4:E4')
        ->and($sheet)->not->toContain('A1:AV1');

    $master = User::factory()->create([
        'user_type' => 'admin',
        'username' => 'master-report',
        'name' => 'Master',
        'last_name' => 'User',
    ]);
    $this->actingAs($master);

    $this->get(route('admin::quality.incoming.reports.show', $report))
        ->assertOk()
        ->assertSee(route('admin::quality.incoming.reports.edit', $report), false);

    Volt::test('admin.quality.incoming.report-form-page', ['report' => $report->id])
        ->assertSet('reportId', $report->id)
        ->assertSet('assembly_name', 'Front axle')
        ->assertSee('Are you sure?')
        ->assertSee('Update this inspection report?')
        ->assertSee('Yes, update it')
        ->set('assembly_name', 'Rear bracket')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect();

    $report->refresh();

    $report->samplingPlan->update(['sample_size' => 99]);

    expect($report->fresh()->samples->firstWhere('characteristic_class', 'C')->sample_size)->toBe(13);

    $this->get(route('admin::quality.incoming.reports.show', $report))
        ->assertOk()
        ->assertSee('8.67%');

    expect($report->assembly_name)->toBe('Rear bracket')
        ->and($report->report_number)->toBe($number)
        ->and($report->inspected_by)->toBe($incomingId)
        ->and($report->fresh()->samples->firstWhere('characteristic_class', 'C')->sample_size)->toBe(13);

    Volt::test('admin.quality.incoming.report-form-page', ['report' => $report->id])
        ->set('reportId', null)
        ->assertSet('reportNumber', $number)
        ->set('assembly_name', 'Lost id update')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect();

    $report->refresh();

    expect($report->assembly_name)->toBe('Lost id update')
        ->and($report->report_number)->toBe($number)
        ->and($report->samples->firstWhere('characteristic_class', 'C')->sample_size)->toBe(13)
        ->and(InspectionReport::query()->where('report_number', $number)->count())->toBe(1);

    $lotB = IncomingLot::query()->where('lot_number', 'L-BRK-250A')->firstOrFail();

    Volt::test('admin.quality.incoming.report-form-page')
        ->call('selectPart', $lotB->part_id)
        ->set('vendor_id', (string) $lotB->vendor_id)
        ->set('invoice_id', (string) $lotB->invoiceLine->purchase_invoice_id)
        ->set('lot_id', (string) $lotB->id)
        ->set('accepted_qty', Qty::format($lotB->qty))
        ->set('checks.0.umac.0', '12.0')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect();

    $created = InspectionReport::query()->where('incoming_lot_id', $lotB->id)->latest('id')->firstOrFail();

    expect($created->report_number)->not->toBe($number)
        ->and(InspectionReport::query()->where('report_number', $created->report_number)->count())->toBe(1);

    Volt::test('admin.quality.incoming.reports-page')
        ->call('deleteReport', $report->id);

    expect(InspectionReport::query()->find($report->id))->toBeNull();
});
