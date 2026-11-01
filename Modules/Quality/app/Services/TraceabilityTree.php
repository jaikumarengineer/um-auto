<?php

namespace Modules\Quality\Services;

use Modules\Quality\Models\IncomingLot;
use Modules\Quality\Models\IncomingPart;
use Modules\Quality\Models\PurchaseInvoice;
use Modules\Quality\Models\PurchaseOrder;
use Modules\Quality\Support\Qty;

class TraceabilityTree
{
    /**
     * @return array<string, mixed>|null
     */
    public function forPart(IncomingPart $part, bool $linkOrders = true, bool $includeReports = true): ?array
    {
        if (! $linkOrders) {
            return $this->inspectionPartTree($part);
        }

        $orders = PurchaseOrder::query()
            ->with(['vendor', 'lines.part', 'lines.invoiceLines.invoice', 'lines.invoiceLines.lots.part', 'lines.invoiceLines.lots.reports'])
            ->whereHas('lines', fn ($query) => $query->where('part_id', $part->id))
            ->orderByDesc('po_date')
            ->get();

        return [
            'title' => $part->part_number,
            'meta' => $part->name,
            'tone' => 'part',
            'url' => null,
            'children' => $orders->map(function (PurchaseOrder $order) use ($part, $linkOrders, $includeReports) {
                $line = $order->lines->firstWhere('part_id', $part->id);

                return [
                    'title' => $order->po_number,
                    'meta' => ($order->vendor?->name ?? '').' · ordered '.Qty::format($line?->ordered_qty).' · '.$order->status,
                    'tone' => 'po',
                    'url' => $linkOrders ? route('admin::quality.purchase.orders.format', $order) : null,
                    'children' => $line
                        ? $line->invoiceLines->map(fn ($invoiceLine) => $this->invoiceNode($invoiceLine->invoice, $invoiceLine, $linkOrders, $includeReports))->all()
                        : [],
                ];
            })->all(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function forLot(IncomingLot $lot, bool $linkOrders = true, bool $includeReports = true): ?array
    {
        $lot->load(['part', 'vendor', 'reports.standard', 'invoiceLine.invoice.purchaseOrder.vendor']);
        $invoice = $lot->invoiceLine?->invoice;
        $order = $invoice?->purchaseOrder;

        $children = $includeReports ? $lot->reports->map(fn ($report) => [
            'title' => $report->report_number,
            'meta' => 'Inspection · '.$report->result.' · '.$report->inspected_at?->format('d M Y H:i'),
            'tone' => 'report',
            'url' => route('admin::quality.incoming.reports.show', $report),
            'children' => [],
        ])->all() : [];

        if ($linkOrders && $invoice) {
            array_unshift($children, [
                'title' => $invoice->invoice_number,
                'meta' => 'Purchase invoice · '.$invoice->invoice_date?->format('d M Y'),
                'tone' => 'pi',
                'url' => route('admin::quality.purchase.invoices.format', $invoice),
                'children' => $order ? [[
                    'title' => $order->po_number,
                    'meta' => 'Purchase order · '.($order->vendor?->name ?? ''),
                    'tone' => 'po',
                    'url' => route('admin::quality.purchase.orders.format', $order),
                    'children' => [],
                ]] : [],
            ]);
        }

        return [
            'title' => $lot->lot_number,
            'meta' => ($lot->part?->part_number ?? '').' · '.Qty::format($lot->qty).' · '.$lot->status,
            'tone' => 'lot',
            'url' => null,
            'children' => $children,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function inspectionPartTree(IncomingPart $part): array
    {
        $lots = IncomingLot::query()
            ->with('reports')
            ->where('part_id', $part->id)
            ->orderBy('lot_number')
            ->get();

        return [
            'title' => $part->part_number,
            'meta' => $part->name,
            'tone' => 'part',
            'url' => null,
            'children' => $lots->map(fn (IncomingLot $lot) => [
                'title' => $lot->lot_number,
                'meta' => Qty::format($lot->qty).' · '.$lot->status,
                'tone' => 'lot',
                'url' => null,
                'children' => $lot->reports->map(fn ($report) => [
                    'title' => $report->report_number,
                    'meta' => $report->result.' · '.$report->inspected_at?->format('d M Y'),
                    'tone' => 'report',
                    'url' => route('admin::quality.incoming.reports.show', $report),
                    'children' => [],
                ])->all(),
            ])->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function invoiceNode(?PurchaseInvoice $invoice, $invoiceLine, bool $linkOrders = true, bool $includeReports = true): array
    {
        return [
            'title' => $invoice?->invoice_number ?? __('Invoice'),
            'meta' => 'Invoiced '.Qty::format($invoiceLine->invoiced_qty).' · '.$invoice?->invoice_date?->format('d M Y'),
            'tone' => 'pi',
            'url' => $linkOrders && $invoice ? route('admin::quality.purchase.invoices.format', $invoice) : null,
            'children' => $invoiceLine->lots->map(fn (IncomingLot $lot) => [
                'title' => $lot->lot_number,
                'meta' => Qty::format($lot->qty).' · '.$lot->status,
                'tone' => 'lot',
                'url' => null,
                'children' => $includeReports ? $lot->reports->map(fn ($report) => [
                    'title' => $report->report_number,
                    'meta' => $report->result.' · '.$report->inspected_at?->format('d M Y'),
                    'tone' => 'report',
                    'url' => route('admin::quality.incoming.reports.show', $report),
                    'children' => [],
                ])->all() : [],
            ])->all(),
        ];
    }
}
