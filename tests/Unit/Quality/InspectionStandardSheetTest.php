<?php

use Modules\Quality\Support\InspectionStandardSheet;
use Tests\TestCase;

uses(TestCase::class);

it('reads the incoming inspection standard workbook into form fields', function () {
    $sheet = InspectionStandardSheet::read(base_path('tests/fixtures/incoming-inspection-standard.xlsx'));

    expect($sheet['code'])->toBe('WR-01')
        ->and($sheet['supplier_name'])->toBe('AAR AAR TECHNOPLAST')
        ->and($sheet['supplier_code'])->toBeNull()
        ->and($sheet['doc_no'])->toBe('F/QA/02')
        ->and($sheet['page_label'])->toBe('1 of 1')
        ->and($sheet['revision'])->toBe('1')
        ->and($sheet['effective_from'])->toBe('2024-11-12')
        ->and($sheet['model'])->toBe('ACLP')
        ->and($sheet['drawing_no'])->toBe('1258-01-002')
        ->and($sheet['drawing_rev'])->toBe('0')
        ->and($sheet['lines'])->toHaveCount(11)
        ->and($sheet['lines'][0]['name'])->toBe('Dimension')
        ->and($sheet['lines'][0]['specification'])->toBe('144±0.2')
        ->and($sheet['lines'][0]['method'])->toBe('D.V.C.')
        ->and($sheet['lines'][0]['sample_plan'])->toBe('Doc.No. ML-08')
        ->and($sheet['lines'][0]['inspection_stage'])->toBe('IQC')
        ->and($sheet['lines'][9]['name'])->toBe('Fitment with assy')
        ->and($sheet['lines'][10]['name'])->toBe('Appearance')
        ->and($sheet['lines'][10]['specification'])->toContain('MOULDING DEFECTS');
});
