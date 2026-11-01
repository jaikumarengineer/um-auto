<?php

use App\Helpers\FileUploadHelper;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;
use Modules\Quality\Database\Seeders\QualityDatabaseSeeder;
use Modules\Quality\Models\IncomingPart;
use Modules\Quality\Models\InspectionStandard;

it('keeps one standard per part and stores an excel or pdf file', function () {
    Storage::fake('public_uploads');
    $this->seed(QualityDatabaseSeeder::class);

    $this->actingAs(User::factory()->create([
        'user_type' => 'incoming',
        'username' => 'part-standard-user',
    ]));

    $part = IncomingPart::query()->where('part_number', 'HSG-CAL-088')->firstOrFail();

    Volt::test('admin.quality.incoming.parts-page')
        ->assertSee('Incoming standard')
        ->assertDontSee('>Standards<', false);

    Volt::test('admin.quality.incoming.part-standard-page', ['part' => $part->id])
        ->assertSet('supplier_name', $part->vendor->name)
        ->assertSet('supplier_code', $part->vendor->code)
        ->set('code', 'WR-01')
        ->set('revision', '1')
        ->set('effective_from', '2024-11-12')
        ->set('model', 'ACLP')
        ->set('lines.0.name', 'Dimension')
        ->set('lines.0.specification', '144±0.2')
        ->set('lines.0.method', 'D.V.C.')
        ->set('lines.0.instrument', 'D.V.C.')
        ->set('lines.0.sample_plan', 'Doc.No. ML-08')
        ->set('lines.0.inspection_stage', 'IQC')
        ->set('document', FileUploadHelper::StoreMedia(UploadedFile::fake()->create('standard.pdf', 20, 'application/pdf'), 'documents')->url)
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('Generate PDF')
        ->assertSee('Generate Excel');

    $standard = InspectionStandard::query()->where('part_id', $part->id)->firstOrFail();

    expect(InspectionStandard::query()->where('part_id', $part->id)->count())->toBe(1)
        ->and($standard->code)->toBe('WR-01')
        ->and($standard->title)->toBe('ACLP')
        ->and($standard->document_name)->toBe('standard.pdf')
        ->and($standard->characteristics)->toHaveCount(1)
        ->and($standard->characteristics->first()->sample_plan)->toBe('Doc.No. ML-08');

    $this->get(route('admin::quality.incoming.parts.standard.document', $part))
        ->assertOk();
    $this->get(route('admin::quality.incoming.parts.standard.pdf', $part))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
    $this->get(route('admin::quality.incoming.parts.standard.excel', $part))
        ->assertOk();

    Volt::test('admin.quality.incoming.part-standard-page', ['part' => $part->id])
        ->set('code', 'WR-02')
        ->set('document', 'uploads/documents/notes.txt')
        ->call('save')
        ->assertHasErrors(['document']);

    expect(InspectionStandard::query()->where('part_id', $part->id)->count())->toBe(1);

    Volt::test('admin.quality.incoming.part-standard-page', ['part' => $part->id])
        ->set('code', 'WR-02')
        ->call('save')
        ->assertHasNoErrors();

    expect(InspectionStandard::query()->where('part_id', $part->id)->count())->toBe(1)
        ->and(InspectionStandard::query()->where('part_id', $part->id)->value('code'))->toBe('WR-02')
        ->and($standard->fresh()->characteristics)->toHaveCount(1);
});

it('fills the standard form from an uploaded inspection workbook', function () {
    Storage::fake('public_uploads');
    $this->seed(QualityDatabaseSeeder::class);

    $this->actingAs(User::factory()->create([
        'user_type' => 'incoming',
        'username' => 'part-standard-import',
    ]));

    $part = IncomingPart::query()->where('part_number', 'HSG-CAL-088')->firstOrFail();
    $upload = new UploadedFile(
        base_path('tests/fixtures/incoming-inspection-standard.xlsx'),
        'standard.xlsx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        null,
        true,
    );
    $media = FileUploadHelper::StoreMedia($upload, 'documents');

    Volt::test('admin.quality.incoming.part-standard-page', ['part' => $part->id])
        ->set('document', $media->url)
        ->assertSet('code', 'WR-01')
        ->assertSet('doc_no', 'F/QA/02')
        ->assertSet('page_label', '1 of 1')
        ->assertSet('revision', '1')
        ->assertSet('effective_from', '2024-11-12')
        ->assertSet('model', 'ACLP')
        ->assertSet('drawing_no', '1258-01-002')
        ->assertSet('drawing_rev', '0')
        ->assertSet('supplier_name', 'AAR AAR TECHNOPLAST')
        ->assertSet('partName', $part->name)
        ->assertCount('lines', 11)
        ->assertSet('lines.0.specification', '144±0.2')
        ->assertSet('lines.0.method', 'D.V.C.')
        ->assertSet('lines.10.name', 'Appearance')
        ->call('save')
        ->assertHasNoErrors();

    $standard = InspectionStandard::query()->where('part_id', $part->id)->firstOrFail();

    expect($standard->characteristics)->toHaveCount(11)
        ->and($standard->title)->toBe('ACLP');

    $excel = $this->get(route('admin::quality.incoming.parts.standard.excel', $part));
    $excel->assertOk();

    $zip = new ZipArchive;
    $zip->open($excel->baseResponse->getFile()->getPathname());

    expect($zip->getFromName('xl/worksheets/sheet1.xml'))->toContain('WR-01')
        ->and($zip->getFromName('xl/worksheets/sheet1.xml'))->toContain('mergeCell')
        ->and($zip->getFromName('xl/styles.xml'))->toContain('FFF4F4F5');

    $zip->close();

    $html = view('quality::documents.inspection-standard', [
        'standard' => $standard->load('characteristics'),
        'part' => $part,
    ])->render();

    expect($html)->toContain('#f4f4f5')
        ->and($html)->toContain('width: 7.5%')
        ->and($html)->toContain('144±0.2');
});
