<?php

namespace Modules\Quality\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Quality\Models\ApprovedVendor;
use Modules\Quality\Models\IncomingLot;
use Modules\Quality\Models\InspectionReport;
use Modules\Quality\Models\InspectionResult;
use Modules\Quality\Models\PurchaseInvoice;
use Modules\Quality\Models\PurchaseInvoiceLine;
use Modules\Quality\Models\PurchaseOrder;
use Modules\Quality\Models\PurchaseOrderLine;
use Modules\Quality\Models\SamplingPlan;
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

            if (! $vendor || ! $vendor->isApproved()) {
                throw ValidationException::withMessages([
                    'vendor_id' => __('Choose an approved vendor.'),
                ]);
            }

            $normalized = $this->normalizeOrderLines($lines);

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

            $invoice ??= new PurchaseInvoice;
            $invoice->fill([
                'invoice_number' => trim((string) $header['invoice_number']),
                'purchase_order_id' => $order->id,
                'vendor_id' => $order->vendor_id,
                'invoice_date' => $header['invoice_date'],
                'status' => 'verified',
                'remarks' => $header['remarks'] ?: null,
            ]);
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
    public function saveInspectionReport(array $header, array $checks): InspectionReport
    {
        return DB::transaction(function () use ($header, $checks) {
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
            $result = $header['result'] ?? 'pass';
            $qty = Qty::num($lot->qty);
            $rejected = match ($result) {
                'fail' => $qty,
                'conditional' => min($qty, Qty::num($header['rejected_qty'] ?? 0)),
                default => 0.0,
            };

            $report = InspectionReport::query()->create([
                'report_number' => trim((string) $header['report_number']),
                'incoming_lot_id' => $lot->id,
                'inspection_standard_id' => $standard->id,
                'sampling_plan_id' => $plan?->id,
                'sample_size' => (int) ($header['sample_size'] ?: ($plan?->sample_size ?? 0)),
                'inspected_qty' => $qty,
                'accepted_qty' => Qty::num($qty - $rejected),
                'rejected_qty' => $rejected,
                'result' => $result,
                'inspected_at' => $header['inspected_at'] ?: now(),
                'remarks' => $header['remarks'] ?: null,
            ]);

            foreach ($standard->characteristics as $index => $characteristic) {
                $check = $checks[$index] ?? [];
                $measured = trim((string) ($check['measured'] ?? ''));
                $judged = $characteristic->judge($measured);
                $checkResult = $judged ?? (($check['result'] ?? 'ok') === 'ng' ? 'ng' : 'ok');

                InspectionResult::query()->create([
                    'inspection_report_id' => $report->id,
                    'inspection_characteristic_id' => $characteristic->id,
                    'characteristic_name' => $characteristic->name,
                    'specification' => $characteristic->specification,
                    'measured_value' => $measured !== '' ? $measured : null,
                    'result' => $checkResult,
                    'is_critical' => $characteristic->is_critical,
                    'remarks' => $check['remarks'] ?: null,
                ]);
            }

            $lot->applyResult($result);

            return $report->fresh(['lot.part', 'standard', 'samplingPlan', 'results']);
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
}
