<?php

namespace Modules\Quality\Database\Seeders;

use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Quality\Models\ApprovedVendor;
use Modules\Quality\Models\IncomingLot;
use Modules\Quality\Models\IncomingPart;
use Modules\Quality\Models\InspectionReport;
use Modules\Quality\Models\InspectionResult;
use Modules\Quality\Models\InspectionStandard;
use Modules\Quality\Models\PartCategory;
use Modules\Quality\Models\PurchaseInvoice;
use Modules\Quality\Models\PurchaseOrder;
use Modules\Quality\Models\PurchaseOrderLine;
use Modules\Quality\Models\SamplingPlan;
use Modules\Quality\Services\QualityDigest;
use Modules\Quality\Support\Ml08SamplingPlan;

class QualityDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (PurchaseOrder::query()->where('po_number', 'PO-2026-0142')->exists()) {
            return;
        }

        DB::transaction(function () {
            $vendors = $this->vendors();
            $categories = $this->categories();
            $parts = $this->parts($vendors, $categories);
            $plans = $this->samplingPlans();
            $standards = $this->standards($parts, $plans);
            $this->orders($vendors, $parts, $standards);
            app(QualityDigest::class)->generate(now()->subDay(), sendMail: false);
        });
    }

    /**
     * @return array<string, ApprovedVendor>
     */
    private function vendors(): array
    {
        $rows = [
            'apex' => ['V-1042', 'Apex Precision Castings', 'approved', 'Meera Shah', 'meera@apex-castings.test', '27AAPCA1234F1Z5'],
            'helix' => ['V-1188', 'Helix Fasteners', 'approved', 'Arun Desai', 'arun@helix-fasteners.test', '24AABCH5678Q1Z2'],
            'nova' => ['V-1204', 'Nova Elastomers', 'approved', 'Kavita Rao', 'kavita@nova-elastomers.test', '29AABCN9012P1Z8'],
            'bright' => ['V-1310', 'Bright Polymers', 'pending', 'Imran Qureshi', 'imran@bright-polymers.test', null],
        ];

        $vendors = [];

        foreach ($rows as $key => [$code, $name, $status, $contact, $email, $gstin]) {
            $vendors[$key] = ApprovedVendor::query()->create([
                'code' => $code,
                'name' => $name,
                'contact_name' => $contact,
                'email' => $email,
                'phone' => '+91 98000 11'.substr($code, -3),
                'address' => 'Plot 12, Industrial Area, Pune',
                'gstin' => $gstin,
                'status' => $status,
                'approved_on' => $status === 'approved' ? now()->subMonths(6)->toDateString() : null,
                'notes' => $status === 'pending' ? 'Waiting for process audit.' : null,
            ]);
        }

        return $vendors;
    }

    /**
     * @return array<string, PartCategory>
     */
    private function categories(): array
    {
        $categories = [];

        foreach (['Brakes', 'Electronics', 'Casting', 'Fasteners', 'Rubber'] as $name) {
            $categories[$name] = PartCategory::query()->create(['name' => $name]);
        }

        return $categories;
    }

    /**
     * @param  array<string, ApprovedVendor>  $vendors
     * @param  array<string, PartCategory>  $categories
     * @return array<string, IncomingPart>
     */
    private function parts(array $vendors, array $categories): array
    {
        $rows = [
            'pad' => ['BRK-PAD-220', 'Front brake pad set', 'Brakes', 'apex', 'PCS', 'C'],
            'sensor' => ['SNR-ABS-014', 'Wheel speed sensor', 'Electronics', 'apex', 'PCS', 'B'],
            'housing' => ['HSG-CAL-088', 'Caliper housing', 'Casting', 'apex', 'PCS', 'D'],
            'bolt' => ['BLT-M10-55', 'Flange bolt M10 x 55', 'Fasteners', 'helix', 'PCS', 'E'],
            'seal' => ['SEL-OIL-032', 'Oil seal 32 mm', 'Rubber', 'nova', 'PCS', 'A'],
            'bush' => ['BSH-SUS-210', 'Suspension bush', 'Rubber', 'nova', 'PCS', 'B'],
        ];

        $parts = [];

        foreach ($rows as $key => [$number, $name, $category, $vendor, $uom, $revision]) {
            $parts[$key] = IncomingPart::query()->create([
                'part_number' => $number,
                'name' => $name,
                'description' => $name.' for the incoming inspection demo.',
                'uom' => $uom,
                'category_id' => $categories[$category]->id,
                'vendor_id' => $vendors[$vendor]->id,
                'approved_on' => now()->subMonths(3)->toDateString(),
                'drawing_revision' => $revision,
                'is_active' => true,
            ]);
        }

        return $parts;
    }

    /**
     * @return array<string, SamplingPlan>
     */
    private function samplingPlans(): array
    {
        Ml08SamplingPlan::ensureLoaded();

        $plans = [
            'pad' => SamplingPlan::forQuantity(1000),
            'sensor' => SamplingPlan::forQuantity(80),
            'bolt' => SamplingPlan::forQuantity(1000),
        ];

        if (in_array(null, $plans, true)) {
            throw new \RuntimeException('ML-08 sampling bands are missing.');
        }

        return $plans;
    }

    /**
     * @param  array<string, IncomingPart>  $parts
     * @param  array<string, SamplingPlan>  $plans
     * @return array<string, InspectionStandard>
     */
    private function standards(array $parts, array $plans): array
    {
        $pad = $this->standard($parts['pad'], $plans['pad'], 'IS-BRK-PAD-220', 'Incoming standard · front brake pad', [
            ['Thickness', '12.0 ± 0.3 mm', 'Variable', 'Micrometer', 11.7, 12.3, 'mm', true, 'C'],
            ['Hardness', '60–80 HRR', 'Variable', 'Hardness tester', 60, 80, 'HRR', false, 'M'],
            ['Friction face', 'No cracks, chips, or glaze', 'Visual', 'Visual', null, null, null, false, 'O'],
            ['Identification', 'Part number and batch stamp present', 'Visual', 'Visual', null, null, null, false, 'Mi'],
        ]);

        $sensor = $this->standard($parts['sensor'], $plans['sensor'], 'IS-SNR-ABS-014', 'Incoming standard · wheel speed sensor', [
            ['Resistance', '1.0–1.4 kΩ', 'Variable', 'Multimeter', 1, 1.4, 'kΩ', true],
            ['Connector', 'Pins straight and lock clicks', 'Visual', 'Visual', null, null, null, true],
            ['Output', 'Clean square wave when spun', 'Functional', 'Oscilloscope', null, null, null, true],
        ]);

        $bolt = $this->standard($parts['bolt'], $plans['bolt'], 'IS-BLT-M10-55', 'Incoming standard · flange bolt', [
            ['Thread', 'M10 x 1.5 GO / NO-GO', 'Attribute', 'Thread gauge', null, null, null, true],
            ['Length', '55 ± 0.4 mm', 'Variable', 'Caliper', 54.6, 55.4, 'mm', true],
            ['Grade mark', '10.9 head mark legible', 'Visual', 'Visual', null, null, null, false],
            ['Coating', '8–15 µm', 'Variable', 'Coating gauge', 8, 15, 'µm', false],
        ]);

        return compact('pad', 'sensor', 'bolt');
    }

    /**
     * @param  array<int, array{0: string, 1: string, 2: string, 3: string, 4: float|null, 5: float|null, 6: string|null, 7: bool}>  $checks
     */
    private function standard(IncomingPart $part, SamplingPlan $plan, string $code, string $title, array $checks): InspectionStandard
    {
        $standard = InspectionStandard::query()->create([
            'code' => $code,
            'part_id' => $part->id,
            'sampling_plan_id' => $plan->id,
            'revision' => 'A',
            'title' => $title,
            'effective_from' => now()->subMonths(2)->toDateString(),
            'status' => 'active',
            'notes' => null,
            'supplier_name' => $part->vendor?->name,
            'supplier_code' => $part->vendor?->code,
            'doc_no' => 'F/QA/02',
            'page_label' => '1 of 1',
            'drawing_rev' => $part->drawing_revision,
        ]);

        $standard->revisions()->create([
            'sequence' => 1,
            'rev_no' => 'A',
            'rev_date' => $standard->effective_from,
            'description' => 'Initial standard',
        ]);

        foreach ($checks as $index => $row) {
            [$name, $spec, $method, $instrument, $min, $max, $unit, $critical] = $row;
            $class = $row[8] ?? ($critical ? 'C' : 'M');

            $standard->characteristics()->create([
                'sequence' => $index + 1,
                'name' => $name,
                'specification' => $spec,
                'method' => $method,
                'instrument' => $instrument,
                'tolerance_min' => $min,
                'tolerance_max' => $max,
                'unit' => $unit,
                'characteristic_class' => $class,
                'is_critical' => $critical,
            ]);
        }

        return $standard->load('characteristics');
    }

    /**
     * @param  array<string, ApprovedVendor>  $vendors
     * @param  array<string, IncomingPart>  $parts
     * @param  array<string, InspectionStandard>  $standards
     */
    private function orders(array $vendors, array $parts, array $standards): void
    {
        $partial = PurchaseOrder::query()->create([
            'po_number' => 'PO-2026-0142',
            'vendor_id' => $vendors['apex']->id,
            'po_date' => now()->subDays(12)->toDateString(),
            'expected_date' => now()->subDays(2)->toDateString(),
            'status' => 'open',
            'currency' => 'INR',
            'remarks' => 'Manual register. Ready for a later SAP / ERP feed.',
        ]);

        $padLine = $partial->lines()->create([
            'part_id' => $parts['pad']->id,
            'line_no' => 1,
            'ordered_qty' => 1000,
            'uom' => 'PCS',
            'unit_price' => 420,
        ]);
        $sensorLine = $partial->lines()->create([
            'part_id' => $parts['sensor']->id,
            'line_no' => 2,
            'ordered_qty' => 200,
            'uom' => 'PCS',
            'unit_price' => 860,
        ]);

        $firstInvoice = $this->invoice($partial, 'PI-8821', now()->subDays(4), [
            [$padLine, 400, [
                ['L-BRK-250A', 250],
                ['L-BRK-150B', 150],
            ]],
            [$sensorLine, 80, [
                ['L-SNR-080C', 80],
            ]],
        ]);

        $this->invoice($partial, 'PI-8840', now(), [
            [$padLine, 350, [
                ['L-BRK-350D', 350],
            ]],
        ]);

        $closed = PurchaseOrder::query()->create([
            'po_number' => 'PO-2026-0155',
            'vendor_id' => $vendors['helix']->id,
            'po_date' => now()->subDays(20)->toDateString(),
            'expected_date' => now()->subDays(10)->toDateString(),
            'status' => 'open',
            'currency' => 'INR',
            'remarks' => null,
        ]);
        $boltLine = $closed->lines()->create([
            'part_id' => $parts['bolt']->id,
            'line_no' => 1,
            'ordered_qty' => 2000,
            'uom' => 'PCS',
            'unit_price' => 6.5,
        ]);
        $this->invoice($closed, 'PI-8790', now()->subDays(9), [
            [$boltLine, 2000, [
                ['L-BLT-1200E', 1200],
                ['L-BLT-800F', 800],
            ]],
        ]);

        $overdue = PurchaseOrder::query()->create([
            'po_number' => 'PO-2026-0160',
            'vendor_id' => $vendors['nova']->id,
            'po_date' => now()->subDays(8)->toDateString(),
            'expected_date' => now()->subDay()->toDateString(),
            'status' => 'open',
            'currency' => 'INR',
            'remarks' => 'No invoice received yet.',
        ]);
        $overdue->lines()->create([
            'part_id' => $parts['seal']->id,
            'line_no' => 1,
            'ordered_qty' => 500,
            'uom' => 'PCS',
            'unit_price' => 18,
        ]);
        $overdue->lines()->create([
            'part_id' => $parts['bush']->id,
            'line_no' => 2,
            'ordered_qty' => 300,
            'uom' => 'PCS',
            'unit_price' => 42,
        ]);

        $draft = PurchaseOrder::query()->create([
            'po_number' => 'PO-2026-0168',
            'vendor_id' => $vendors['apex']->id,
            'po_date' => now()->toDateString(),
            'expected_date' => now()->addDays(14)->toDateString(),
            'status' => 'draft',
            'currency' => 'INR',
            'remarks' => 'Draft, not released to the vendor.',
        ]);
        $draft->lines()->create([
            'part_id' => $parts['housing']->id,
            'line_no' => 1,
            'ordered_qty' => 100,
            'uom' => 'PCS',
            'unit_price' => 980,
        ]);

        $this->report('IR-2026-0001', 'L-BRK-250A', $standards['pad'], 'pass', now()->subDays(3), [
            ['12.05', 'ok'],
            ['68', 'ok'],
            ['No cracks', 'ok'],
            ['Stamp clear', 'ok'],
        ], 32);
        $this->report('IR-2026-0002', 'L-BRK-150B', $standards['pad'], 'conditional', now()->subDays(3), [
            ['12.10', 'ok'],
            ['84', 'ng'],
            ['No cracks', 'ok'],
            ['Stamp clear', 'ok'],
        ], 20, 'Hardness is above the band. Lot accepted with a deviation note.');
        $this->report('IR-2026-0003', 'L-BLT-1200E', $standards['bolt'], 'pass', now()->subDays(8), [
            ['GO pass', 'ok'],
            ['55.1', 'ok'],
            ['10.9 clear', 'ok'],
            ['11', 'ok'],
        ], 80);
        $this->report('IR-2026-0004', 'L-BLT-800F', $standards['bolt'], 'fail', now()->subDays(8), [
            ['NO-GO fail', 'ng'],
            ['55.0', 'ok'],
            ['10.9 clear', 'ok'],
            ['10', 'ok'],
        ], 80, 'Thread gauge failed. Batch is on quality hold.');

        $partial->syncFulfillmentStatus();
        $closed->syncFulfillmentStatus();
        $overdue->syncFulfillmentStatus();
    }

    /**
     * @param  array<int, array{0: PurchaseOrderLine, 1: float|int, 2: array<int, array{0: string, 1: float|int}>}>  $lines
     */
    private function invoice(PurchaseOrder $order, string $number, CarbonInterface $date, array $lines): PurchaseInvoice
    {
        $invoice = PurchaseInvoice::query()->create([
            'invoice_number' => $number,
            'purchase_order_id' => $order->id,
            'vendor_id' => $order->vendor_id,
            'invoice_date' => $date->toDateString(),
            'status' => 'verified',
            'remarks' => null,
        ]);

        foreach ($lines as [$poLine, $qty, $lots]) {
            $invoiceLine = $invoice->lines()->create([
                'purchase_order_line_id' => $poLine->id,
                'part_id' => $poLine->part_id,
                'invoiced_qty' => $qty,
            ]);

            foreach ($lots as [$lotNumber, $lotQty]) {
                IncomingLot::query()->create([
                    'lot_number' => $lotNumber,
                    'purchase_invoice_line_id' => $invoiceLine->id,
                    'part_id' => $poLine->part_id,
                    'vendor_id' => $order->vendor_id,
                    'qty' => $lotQty,
                    'received_at' => $date->copy()->setTime(9, 30),
                    'status' => 'received',
                ]);
            }
        }

        return $invoice;
    }

    /**
     * @param  array<int, array{0: string, 1: string}>  $measurements
     */
    private function report(
        string $number,
        string $lotNumber,
        InspectionStandard $standard,
        string $result,
        CarbonInterface $when,
        array $measurements,
        int $sampleSize,
        ?string $remarks = null,
    ): void {
        $lot = IncomingLot::query()->where('lot_number', $lotNumber)->firstOrFail();
        $plan = SamplingPlan::forQuantity((float) $lot->qty);
        $qty = (float) $lot->qty;
        $rejected = match ($result) {
            'fail' => $qty,
            'conditional' => 0.0,
            default => 0.0,
        };

        $report = InspectionReport::query()->create([
            'report_number' => $number,
            'incoming_lot_id' => $lot->id,
            'inspection_standard_id' => $standard->id,
            'sampling_plan_id' => $plan?->id ?? $standard->sampling_plan_id,
            'sample_size' => $sampleSize,
            'inspected_qty' => $qty,
            'accepted_qty' => $result === 'fail' ? 0 : $qty,
            'rejected_qty' => $rejected,
            'result' => $result,
            'inspected_at' => $when->copy()->setTime(15, 10),
            'remarks' => $remarks,
        ]);

        foreach ($standard->characteristics as $index => $characteristic) {
            [$measured, $check] = $measurements[$index];

            InspectionResult::query()->create([
                'inspection_report_id' => $report->id,
                'inspection_characteristic_id' => $characteristic->id,
                'characteristic_name' => $characteristic->name,
                'specification' => $characteristic->specification,
                'measured_value' => $measured,
                'result' => $check,
                'is_critical' => $characteristic->is_critical,
                'remarks' => null,
            ]);
        }

        $lot->applyResult($result);
    }
}
