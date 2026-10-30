<?php

namespace Modules\Quality\Services;

use App\Services\RecordApproval;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Quality\Models\ApprovedVendor;
use Modules\Quality\Models\IncomingLot;
use Modules\Quality\Models\InspectionCharacteristic;
use Modules\Quality\Models\InspectionReport;
use Modules\Quality\Models\InspectionResult;
use Modules\Quality\Models\PurchaseInvoice;
use Modules\Quality\Models\PurchaseInvoiceLine;
use Modules\Quality\Models\PurchaseOrder;
use Modules\Quality\Models\PurchaseOrderLine;
use Modules\Quality\Models\SamplingPlan;
use Modules\Quality\Support\InspectionReadings;
use Modules\Quality\Support\Qty;

class QualityWorkflow
{
    /**
     * @param  array<string, mixed>  $header
     * @param  array<int, array<string, mixed>>  $lines
     */
    public function savePurchaseOrder(array $header, array $lines, ?PurchaseOrder $order = null): PurchaseOrder
    {
        return DB::transaction(function () use ($header, $lines, $order) {
            $vendor = ApprovedVendor::query()->find($header['vendor_id'] ?? null);

            if (! $vendor || $vendor->status === 'blocked') {
                throw ValidationException::withMessages([
                    'vendor_id' => __('Choose a vendor that is not blocked.'),
                ]);
            }

            $normalized = $this->normalizeOrderLines($lines);

            $creating = ! $order?->exists;
            $order ??= new PurchaseOrder;
            $order->fill([
                'po_number' => trim((string) $header['po_number']),
                'vendor_id' => $vendor->id,
                'po_date' => $header['po_date'],
                'expected_date' => $header['expected_date'] ?: null,
                'currency' => $header['currency'] ?: 'INR',
                'remarks' => $header['remarks'] ?: null,
                'status' => in_array($header['status'] ?? 'open', ['draft', 'cancelled'], true)
                    ? $header['status']
                    : ($order->exists && in_array($order->status, ['partial', 'closed'], true) ? $order->status : 'open'),
            ]);

            if ($creating) {
                app(RecordApproval::class)->stampOnCreate($order);
            }

            $order->save();

            $kept = [];

            foreach ($normalized as $index => $line) {
                $model = null;

                if (! empty($line['id'])) {
                    $model = $order->lines()->whereKey($line['id'])->first();
                }

                if ($model) {
                    $invoiced = $model->invoicedQuantity();

                    if (Qty::exceeds($invoiced, $line['ordered_qty'])) {
                        throw ValidationException::withMessages([
                            "lines.$index.ordered_qty" => __('Ordered qty cannot drop below the :qty already invoiced.', [
                                'qty' => Qty::format($invoiced),
                            ]),
                        ]);
                    }
                } else {
                    $model = new PurchaseOrderLine(['purchase_order_id' => $order->id]);
                }

                $model->fill([
                    'part_id' => $line['part_id'],
                    'line_no' => $index + 1,
                    'ordered_qty' => $line['ordered_qty'],
                    'uom' => $line['uom'],
                    'unit_price' => $line['unit_price'],
                ]);
                $model->save();
                $kept[] = $model->id;
            }

            $removable = $order->lines()->whereNotIn('id', $kept)->get();

            foreach ($removable as $line) {
                if ($line->invoiceLines()->exists()) {
                    throw ValidationException::withMessages([
                        'lines' => __('A removed line already has purchase invoices.'),
                    ]);
                }

                $line->delete();
            }

            $order->syncFulfillmentStatus();

            return $order->fresh(['vendor', 'lines.part', 'lines.invoiceLines']);
        });
    }

    public function deletePurchaseOrder(PurchaseOrder $order): void
    {
        if ($order->invoices()->exists()) {
            throw ValidationException::withMessages([
                'order' => __('This purchase order has invoices. Remove those invoices first.'),
            ]);
        }

        DB::transaction(function () use ($order) {
            $order->lines()->delete();
            $order->delete();
        });
    }

    /**
     * @param  array<string, mixed>  $header
     * @param  array<int, array<string, mixed>>  $lines
     */
    public function savePurchaseInvoice(array $header, array $lines, ?PurchaseInvoice $invoice = null): PurchaseInvoice
    {
        return DB::transaction(function () use ($header, $lines, $invoice) {
            $order = PurchaseOrder::query()->with('lines.part')->find($header['purchase_order_id'] ?? null);

            if (! $order) {
                throw ValidationException::withMessages([
                    'purchase_order_id' => __('Choose a purchase order.'),
                ]);
            }

            if (in_array($order->status, ['cancelled', 'draft'], true)) {
                throw ValidationException::withMessages([
                    'purchase_order_id' => __('Only released purchase orders can be invoiced.'),
                ]);
            }

            if (($order->approval_status ?? RecordApproval::APPROVED) !== RecordApproval::APPROVED) {
                throw ValidationException::withMessages([
                    'purchase_order_id' => __('This purchase order is waiting for approval before invoices can be booked.'),
                ]);
            }

            if ($invoice?->exists && (int) $invoice->purchase_order_id !== (int) $order->id) {
                throw ValidationException::withMessages([
                    'purchase_order_id' => __('This invoice is already tied to a purchase order.'),
                ]);
            }

            if ($invoice && $invoice->hasInspectionReports()) {
                throw ValidationException::withMessages([
                    'invoice' => __('This invoice already has inspection reports, so its lots are locked.'),
                ]);
            }

            $creating = ! $invoice?->exists;
            $invoice ??= new PurchaseInvoice;
            $invoice->fill([
                'invoice_number' => trim((string) $header['invoice_number']),
                'purchase_order_id' => $order->id,
                'vendor_id' => $order->vendor_id,
                'invoice_date' => $header['invoice_date'],
                'status' => 'verified',
                'remarks' => $header['remarks'] ?: null,
            ]);

            if ($creating) {
                app(RecordApproval::class)->stampOnCreate($invoice);
            }

            $invoice->save();

            if ($invoice->wasRecentlyCreated === false) {
                foreach ($invoice->lines()->with('lots')->get() as $existing) {
                    $existing->lots()->delete();
                    $existing->delete();
                }
            }

            $usedLots = [];
            $created = 0;

            foreach (array_values($lines) as $index => $line) {
                $qty = Qty::num($line['invoiced_qty'] ?? 0);
                $lots = $this->normalizeLots($line['lots'] ?? []);

                if ($qty <= 0 && $lots === []) {
                    continue;
                }

                $poLine = $order->lines->firstWhere('id', (int) ($line['purchase_order_line_id'] ?? 0));

                if (! $poLine) {
                    throw ValidationException::withMessages([
                        "lines.$index.invoiced_qty" => __('This line is not on the selected purchase order.'),
                    ]);
                }

                if ($qty <= 0) {
                    throw ValidationException::withMessages([
                        "lines.$index.invoiced_qty" => __('Enter an invoiced quantity, or clear the lots.'),
                    ]);
                }

                $remaining = $poLine->remainingQuantity();

                if (Qty::exceeds($qty, $remaining)) {
                    throw ValidationException::withMessages([
                        "lines.$index.invoiced_qty" => __('Invoiced qty :qty is above the :remaining still open on :po.', [
                            'qty' => Qty::format($qty),
                            'remaining' => Qty::format($remaining),
                            'po' => $order->po_number,
                        ]),
                    ]);
                }

                $lotSum = Qty::num(collect($lots)->sum('qty'));

                if (! Qty::same($lotSum, $qty)) {
                    throw ValidationException::withMessages([
                        "lines.$index.lots" => __('Lot quantities (:lots) must add up to the invoiced quantity (:qty).', [
                            'lots' => Qty::format($lotSum),
                            'qty' => Qty::format($qty),
                        ]),
                    ]);
                }

                foreach ($lots as $lotIndex => $lot) {
                    $number = $lot['lot_number'];

                    if (isset($usedLots[$number]) || IncomingLot::query()->where('lot_number', $number)->exists()) {
                        throw ValidationException::withMessages([
                            "lines.$index.lots.$lotIndex.lot_number" => __('Lot :lot is already used. Every batch needs its own number.', [
                                'lot' => $number,
                            ]),
                        ]);
                    }

                    $usedLots[$number] = true;
                }

                $invoiceLine = PurchaseInvoiceLine::query()->create([
                    'purchase_invoice_id' => $invoice->id,
                    'purchase_order_line_id' => $poLine->id,
                    'part_id' => $poLine->part_id,
                    'invoiced_qty' => $qty,
                ]);

                foreach ($lots as $lot) {
                    IncomingLot::query()->create([
                        'lot_number' => $lot['lot_number'],
                        'purchase_invoice_line_id' => $invoiceLine->id,
                        'part_id' => $poLine->part_id,
                        'vendor_id' => $order->vendor_id,
                        'qty' => $lot['qty'],
                        'received_at' => $header['invoice_date'].' 09:00:00',
                        'status' => 'received',
                    ]);
                }

                $created++;
            }

            if ($created === 0) {
                throw ValidationException::withMessages([
                    'lines' => __('Enter a quantity on at least one purchase order line.'),
                ]);
            }

            $order->syncFulfillmentStatus();

            return $invoice->fresh(['vendor', 'purchaseOrder', 'lines.part', 'lines.lots']);
        });
    }

    public function deletePurchaseInvoice(PurchaseInvoice $invoice): void
    {
        if ($invoice->hasInspectionReports()) {
            throw ValidationException::withMessages([
                'invoice' => __('This invoice has inspection reports and cannot be deleted.'),
            ]);
        }

        DB::transaction(function () use ($invoice) {
            $order = $invoice->purchaseOrder;

            foreach ($invoice->lines()->get() as $line) {
                $line->lots()->delete();
                $line->delete();
            }

            $invoice->delete();
            $order?->syncFulfillmentStatus();
        });
    }

    /**
     * @param  array<string, mixed>  $header
     * @param  array<int, array<string, mixed>>  $checks
     */
    public function saveInspectionReport(array $header, array $checks, ?InspectionReport $existing = null): InspectionReport
    {
        return DB::transaction(function () use ($header, $checks, $existing) {
            $lot = IncomingLot::query()->with('part')->find($header['lot_id'] ?? null);

            if (! $lot) {
                throw ValidationException::withMessages([
                    'lot_id' => __('Choose a batch / lot.'),
                ]);
            }

            $standard = $lot->part?->currentStandard();

            if (! $standard) {
                throw ValidationException::withMessages([
                    'lot_id' => __('This part has no active incoming inspection standard.'),
                ]);
            }

            $standard->load('characteristics');

            if ($standard->characteristics->isEmpty()) {
                throw ValidationException::withMessages([
                    'lot_id' => __('The inspection standard has no characteristics yet.'),
                ]);
            }

            $plan = SamplingPlan::forQuantity((float) $lot->qty) ?? $standard->samplingPlan;
            $sampleSize = (int) ($plan?->sample_size ?: ($header['sample_size'] ?? 1));
            $sampleSize = max(1, $sampleSize);
            $qty = Qty::num($lot->qty);
            $accepted = array_key_exists('accepted_qty', $header)
                ? min($qty, max(0, Qty::num($header['accepted_qty'])))
                : null;
            $supplierDecision = $this->decision($header['supplier_decision'] ?? 'conforming');
            $umacDecision = $this->decision($header['umac_decision'] ?? 'conforming');
            $result = match (true) {
                $supplierDecision === 'non_conforming' || $umacDecision === 'non_conforming' => 'fail',
                $supplierDecision === 'aud' || $umacDecision === 'aud' => 'conditional',
                default => $header['result'] ?? 'pass',
            };

            if ($accepted === null) {
                $rejected = match ($result) {
                    'fail' => $qty,
                    'conditional' => min($qty, Qty::num($header['rejected_qty'] ?? 0)),
                    default => 0.0,
                };
                $accepted = Qty::num($qty - $rejected);
            } else {
                $rejected = Qty::num($qty - $accepted);
            }

            $prepared = [];
            $supplierStatuses = [];
            $umacStatuses = [];

            foreach ($standard->characteristics as $index => $characteristic) {
                $check = $checks[$index] ?? [];
                $limits = InspectionReadings::limits(
                    $characteristic->tolerance_min,
                    $characteristic->tolerance_max,
                    $characteristic->specification,
                );
                $supplierSource = is_array($check['supplier'] ?? null) ? $check['supplier'] : [];
                $umacSource = is_array($check['umac'] ?? null) ? $check['umac'] : [];

                if ($supplierSource === [] && $umacSource === [] && filled($check['measured'] ?? null)) {
                    $supplierSource = [(string) $check['measured']];
                }

                $supplier = InspectionReadings::pad($supplierSource, $sampleSize);
                $umac = InspectionReadings::pad($umacSource, $sampleSize);
                $manual = ! InspectionReadings::hasLimits($limits['min'], $limits['max']);

                $supplierResult = $manual
                    ? InspectionReadings::manualStatus($check['supplier_status'] ?? null)
                    : InspectionReadings::status($supplier, $limits['min'], $limits['max']);
                $umacResult = $manual
                    ? InspectionReadings::manualStatus($check['umac_status'] ?? null)
                    : InspectionReadings::status($umac, $limits['min'], $limits['max']);
                $supplierStatuses[] = $supplierResult;
                $umacStatuses[] = $umacResult;
                $class = InspectionCharacteristic::normalize($check['class'] ?? $characteristic->characteristic_class);

                $prepared[] = [
                    'characteristic' => $characteristic,
                    'class' => $class,
                    'supplier' => $supplier,
                    'umac' => $umac,
                    'supplier_result' => $supplierResult,
                    'umac_result' => $umacResult,
                    'manual' => $manual,
                    'limits' => $limits,
                    'remarks' => trim((string) ($check['remarks'] ?? '')) ?: null,
                ];
            }

            $attributes = [
                'incoming_lot_id' => $lot->id,
                'inspection_standard_id' => $standard->id,
                'sampling_plan_id' => $plan?->id,
                'sample_size' => $sampleSize,
                'inspected_qty' => $qty,
                'accepted_qty' => $accepted,
                'rejected_qty' => $rejected,
                'result' => $result,
                'inspected_at' => $header['inspected_at'] ?: now(),
                'remarks' => $header['remarks'] ?: null,
                'assembly_name' => trim((string) ($header['assembly_name'] ?? '')) ?: null,
                'process' => trim((string) ($header['process'] ?? '')) ?: null,
                'bill_no' => trim((string) ($header['bill_no'] ?? '')) ?: null,
                'supplier_status' => InspectionReadings::rollup($supplierStatuses),
                'umac_status' => InspectionReadings::rollup($umacStatuses),
                'supplier_decision' => $supplierDecision,
                'umac_decision' => $umacDecision,
                'inspected_by' => $header['inspected_by'] ?? auth()->id(),
                'images' => $this->reportImages($header['images'] ?? []),
            ];

            $previousLotId = null;

            if ($existing) {
                $previousLotId = $existing->incoming_lot_id;
                $existing->update($attributes);
                $existing->results()->delete();
                $report = $existing;
            } else {
                $report = new InspectionReport([
                    'report_number' => $this->allocateReportNumber($header['report_number'] ?? null),
                    ...$attributes,
                ]);
                app(RecordApproval::class)->stampOnCreate($report);
                $report->save();
            }

            foreach ($prepared as $row) {
                /** @var InspectionCharacteristic $characteristic */
                $characteristic = $row['characteristic'];

                InspectionResult::query()->create([
                    'inspection_report_id' => $report->id,
                    'inspection_characteristic_id' => $characteristic->id,
                    'characteristic_name' => $characteristic->name,
                    'specification' => $characteristic->specification,
                    'method' => $characteristic->method,
                    'characteristic_class' => $row['class'],
                    'measured_value' => InspectionReadings::summary($row['supplier'], $row['umac'], $row['limits']['min'], $row['limits']['max']),
                    'supplier_readings' => $row['supplier'],
                    'umac_readings' => $row['umac'],
                    'supplier_result' => $this->storedSideResult($row['supplier_result'], (bool) $row['manual']),
                    'umac_result' => $this->storedSideResult($row['umac_result'], (bool) $row['manual']),
                    'result' => ($row['supplier_result'] === 'ng' || $row['umac_result'] === 'ng') ? 'ng' : 'ok',
                    'is_critical' => $row['class'] === 'C',
                    'remarks' => $row['remarks'],
                ]);
            }

            $this->refreshLotStatus($lot);

            if ($previousLotId && (int) $previousLotId !== (int) $lot->id) {
                $previous = IncomingLot::query()->find($previousLotId);

                if ($previous) {
                    $this->refreshLotStatus($previous);
                }
            }

            return $report->fresh(['lot.part', 'standard', 'samplingPlan', 'results']);
        });
    }

    public function deleteInspectionReport(InspectionReport $report): void
    {
        DB::transaction(function () use ($report) {
            $lot = $report->lot;
            $report->results()->delete();
            $report->delete();

            if ($lot) {
                $this->refreshLotStatus($lot);
            }
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     * @return array<int, array{id: int|null, part_id: int, ordered_qty: float, uom: string, unit_price: float}>
     */
    private function normalizeOrderLines(array $lines): array
    {
        $normalized = [];
        $parts = [];

        foreach (array_values($lines) as $index => $line) {
            $partId = (int) ($line['part_id'] ?? 0);

            if ($partId === 0) {
                throw ValidationException::withMessages([
                    "lines.$index.part_id" => __('Choose a part.'),
                ]);
            }

            if (isset($parts[$partId])) {
                throw ValidationException::withMessages([
                    "lines.$index.part_id" => __('This part is already on the purchase order.'),
                ]);
            }

            $parts[$partId] = true;
            $normalized[] = [
                'id' => ! empty($line['id']) ? (int) $line['id'] : null,
                'part_id' => $partId,
                'ordered_qty' => Qty::num($line['ordered_qty'] ?? 0),
                'uom' => trim((string) ($line['uom'] ?? 'PCS')) ?: 'PCS',
                'unit_price' => round((float) ($line['unit_price'] ?? 0), 2),
            ];
        }

        if ($normalized === []) {
            throw ValidationException::withMessages([
                'lines' => __('Add at least one part line.'),
            ]);
        }

        return $normalized;
    }

    /**
     * @param  array<int, array<string, mixed>>  $lots
     * @return array<int, array{lot_number: string, qty: float}>
     */
    private function normalizeLots(array $lots): array
    {
        $normalized = [];

        foreach ($lots as $lot) {
            $number = strtoupper(trim((string) ($lot['lot_number'] ?? '')));
            $qty = Qty::num($lot['qty'] ?? 0);

            if ($number === '' && $qty <= 0) {
                continue;
            }

            if ($number === '' || $qty <= 0) {
                throw ValidationException::withMessages([
                    'lines' => __('Each lot needs a unique number and a quantity.'),
                ]);
            }

            $normalized[] = [
                'lot_number' => $number,
                'qty' => $qty,
            ];
        }

        return $normalized;
    }

    private function storedSideResult(string $status, bool $manual): string
    {
        if ($status === 'empty') {
            return $manual ? 'empty' : 'ok';
        }

        return $status;
    }

    private function refreshLotStatus(IncomingLot $lot): void
    {
        $latest = $lot->reports()
            ->where('approval_status', RecordApproval::APPROVED)
            ->reorder()
            ->orderByDesc('inspected_at')
            ->orderByDesc('id')
            ->first();

        if (! $latest) {
            $lot->status = 'received';
            $lot->save();

            return;
        }

        $lot->applyResult($latest->result);
    }

    public function approvePurchaseOrder(PurchaseOrder $order, ?string $reason = null): PurchaseOrder
    {
        return app(RecordApproval::class)->approve($order, reason: $reason);
    }

    public function rejectPurchaseOrder(PurchaseOrder $order, ?string $reason = null): PurchaseOrder
    {
        return app(RecordApproval::class)->reject($order, reason: $reason);
    }

    public function approvePurchaseInvoice(PurchaseInvoice $invoice, ?string $reason = null): PurchaseInvoice
    {
        return app(RecordApproval::class)->approve($invoice, reason: $reason);
    }

    public function rejectPurchaseInvoice(PurchaseInvoice $invoice, ?string $reason = null): PurchaseInvoice
    {
        return app(RecordApproval::class)->reject($invoice, reason: $reason);
    }

    public function approveInspectionReport(InspectionReport $report, ?string $reason = null): InspectionReport
    {
        $report = app(RecordApproval::class)->approve($report, reason: $reason);
        $report->verified_by = $report->approved_by;
        $report->save();

        if ($report->lot) {
            $this->refreshLotStatus($report->lot);
        }

        return $report->fresh(['lot.part', 'standard', 'samplingPlan', 'results', 'creator', 'approver']);
    }

    public function rejectInspectionReport(InspectionReport $report, ?string $reason = null): InspectionReport
    {
        $report = app(RecordApproval::class)->reject($report, reason: $reason);

        if ($report->lot) {
            $this->refreshLotStatus($report->lot);
        }

        return $report->fresh(['lot.part', 'standard', 'samplingPlan', 'results', 'creator', 'approver']);
    }

    private function decision(mixed $value): string
    {
        $value = (string) $value;

        return in_array($value, ['conforming', 'non_conforming', 'aud'], true) ? $value : 'conforming';
    }

    private function allocateReportNumber(mixed $preferred = null): string
    {
        $preferred = trim((string) $preferred);

        if ($preferred !== '' && ! InspectionReport::query()->where('report_number', $preferred)->exists()) {
            return $preferred;
        }

        $year = now()->format('Y');
        $prefix = 'IR-'.$year.'-';

        $latest = InspectionReport::query()
            ->where('report_number', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('report_number')
            ->value('report_number');

        $sequence = 1;

        if (is_string($latest) && preg_match('/^IR-'.preg_quote($year, '/').'-(\d+)$/', $latest, $matches)) {
            $sequence = (int) $matches[1] + 1;
        }

        do {
            $candidate = $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
            $sequence++;
        } while (InspectionReport::query()->where('report_number', $candidate)->exists());

        return $candidate;
    }

    /**
     * @return list<array{id: int|null, url: string}>
     */
    private function reportImages(mixed $images): array
    {
        if (! is_array($images)) {
            return [];
        }

        $stored = [];

        foreach ($images as $image) {
            $url = is_array($image) ? (string) ($image['url'] ?? '') : (string) $image;
            $url = ltrim(str_replace('\\', '/', $url), '/');

            if ($url === '' || str_contains($url, '..') || ! str_starts_with($url, 'uploads/')) {
                continue;
            }

            $stored[] = [
                'id' => is_array($image) && isset($image['id']) ? (int) $image['id'] : null,
                'url' => $url,
            ];
        }

        return $stored;
    }
}
