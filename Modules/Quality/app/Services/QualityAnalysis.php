<?php

namespace Modules\Quality\Services;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Modules\Quality\Models\IncomingLot;
use Modules\Quality\Models\IncomingPart;
use Modules\Quality\Models\InspectionReport;
use Modules\Quality\Models\PurchaseInvoice;
use Modules\Quality\Models\PurchaseOrder;
use Modules\Quality\Support\Qty;

class QualityAnalysis
{
    public function getYearlyBarChart(): array
    {
        $years = collect(range(2, 0))->map(fn (int $offset) => now()->subYears($offset)->startOfYear());

        return [
            'labels' => $years->map(fn ($year) => $year->format('Y'))->all(),
            'datasets' => [
                [
                    'label' => 'Purchase orders',
                    'data' => $years->map(fn ($year) => PurchaseOrder::query()->whereBetween('po_date', [$year, $year->copy()->endOfYear()])->count())->all(),
                ],
                [
                    'label' => 'Inspection reports',
                    'data' => $years->map(fn ($year) => InspectionReport::query()->whereBetween('inspected_at', [$year, $year->copy()->endOfYear()])->count())->all(),
                ],
            ],
        ];
    }

    public function getMonthlyLineChart(): array
    {
        $startOfMonth = now()->startOfMonth();
        $daysInMonth = now()->daysInMonth;
        $monthDays = collect(range(0, $daysInMonth - 1))->map(fn (int $offset) => $startOfMonth->copy()->addDays($offset));

        return [
            'labels' => $monthDays->map(fn ($day) => $day->format('d M'))->all(),
            'datasets' => [
                [
                    'label' => 'Purchase orders',
                    'data' => $monthDays->map(fn ($day) => PurchaseOrder::query()->whereDate('po_date', $day)->count())->all(),
                ],
                [
                    'label' => 'Inspection reports',
                    'data' => $monthDays->map(fn ($day) => InspectionReport::query()->whereDate('inspected_at', $day)->count())->all(),
                ],
            ],
        ];
    }

    /**
     * Purchase graph by period (day/week/month/year) within a date range.
     *
     * @return array{type: string, title: string, dataset: array{labels: list<string>, datasets: list<array<string, mixed>>}}
     */
    public function purchaseGraph(string $period, string $from, string $to): array
    {
        [$fromDate, $toDate] = $this->normalizeRange($from, $to);

        return match ($period) {
            'week' => [
                'type' => 'bar',
                'title' => __('Weekly'),
                'dataset' => $this->purchaseQtyByWeeks($fromDate, $toDate),
            ],
            'month' => [
                'type' => 'line',
                'title' => __('Monthwise'),
                'dataset' => $this->purchaseQtyByMonths($fromDate, $toDate),
            ],
            'year' => [
                'type' => 'bar',
                'title' => __('Year wise'),
                'dataset' => $this->purchaseQtyByYears($fromDate, $toDate),
            ],
            default => [
                'type' => 'bar',
                'title' => __('Day wise'),
                'dataset' => $this->purchaseQtyByDays($fromDate, $toDate),
            ],
        };
    }

    /**
     * @return array{0: CarbonInterface, 1: CarbonInterface}
     */
    private function normalizeRange(string $from, string $to): array
    {
        $fromDate = rescue(fn () => Carbon::parse($from)->startOfDay(), now()->subDays(29)->startOfDay(), false);
        $toDate = rescue(fn () => Carbon::parse($to)->endOfDay(), now()->endOfDay(), false);

        if ($fromDate->gt($toDate)) {
            return [$toDate->copy()->startOfDay(), $fromDate->copy()->endOfDay()];
        }

        return [$fromDate, $toDate];
    }

    /**
     * @return array{labels: list<string>, datasets: list<array<string, mixed>>}
     */
    private function purchaseQtySeries(
        Collection $buckets,
        string $keyFormat,
        string $labelFormat,
        CarbonInterface $from,
        CarbonInterface $to,
    ): array {
        $ordered = $this->orderedQtyGroupedBy($from, $to, $keyFormat);
        $invoiced = $this->invoicedQtyGroupedBy($from, $to, $keyFormat);

        return [
            'labels' => $buckets->map(fn ($bucket) => $bucket->format($labelFormat))->all(),
            'datasets' => [
                [
                    'label' => 'PO',
                    'data' => $buckets->map(fn ($bucket) => $ordered->get($bucket->format($keyFormat), 0.0))->all(),
                ],
                [
                    'label' => 'PI',
                    'data' => $buckets->map(fn ($bucket) => $invoiced->get($bucket->format($keyFormat), 0.0))->all(),
                ],
            ],
        ];
    }

    /**
     * @return array{labels: list<string>, datasets: list<array<string, mixed>>}
     */
    private function purchaseQtyByDays(CarbonInterface $from, CarbonInterface $to): array
    {
        $days = collect();
        for ($day = $from->copy()->startOfDay(); $day->lte($to->copy()->startOfDay()); $day->addDay()) {
            $days->push($day->copy());
        }

        return $this->purchaseQtySeries($days, 'Y-m-d', 'd M', $from, $to);
    }

    /**
     * @return array{labels: list<string>, datasets: list<array<string, mixed>>}
     */
    private function purchaseQtyByWeeks(CarbonInterface $from, CarbonInterface $to): array
    {
        $weeks = collect();
        for ($week = $from->copy()->startOfWeek(); $week->lte($to->copy()->endOfWeek()); $week->addWeek()) {
            $weeks->push($week->copy());
        }

        $ordered = $this->orderedQtyGroupedBy($from, $to, 'o-W');
        $invoiced = $this->invoicedQtyGroupedBy($from, $to, 'o-W');

        return [
            'labels' => $weeks->map(fn ($week) => $week->format('\WW o'))->all(),
            'datasets' => [
                [
                    'label' => 'PO',
                    'data' => $weeks->map(fn ($week) => $ordered->get($week->format('o-W'), 0.0))->all(),
                ],
                [
                    'label' => 'PI',
                    'data' => $weeks->map(fn ($week) => $invoiced->get($week->format('o-W'), 0.0))->all(),
                ],
            ],
        ];
    }

    /**
     * @return array{labels: list<string>, datasets: list<array<string, mixed>>}
     */
    private function purchaseQtyByMonths(CarbonInterface $from, CarbonInterface $to): array
    {
        $months = collect();
        for ($month = $from->copy()->startOfMonth(); $month->lte($to->copy()->startOfMonth()); $month->addMonth()) {
            $months->push($month->copy());
        }

        return $this->purchaseQtySeries($months, 'Y-m', 'M Y', $from, $to);
    }

    /**
     * @return array{labels: list<string>, datasets: list<array<string, mixed>>}
     */
    private function purchaseQtyByYears(CarbonInterface $from, CarbonInterface $to): array
    {
        $years = collect();
        for ($year = $from->copy()->startOfYear(); $year->lte($to->copy()->startOfYear()); $year->addYear()) {
            $years->push($year->copy());
        }

        return $this->purchaseQtySeries($years, 'Y', 'Y', $from, $to);
    }

    /**
     * @return Collection<string, float>
     */
    private function orderedQtyGroupedBy(CarbonInterface $from, CarbonInterface $to, string $format): Collection
    {
        return PurchaseOrder::query()
            ->with('lines')
            ->where('status', '!=', 'cancelled')
            ->whereBetween('po_date', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->get()
            ->groupBy(fn (PurchaseOrder $order) => $order->po_date?->format($format))
            ->map(fn ($group) => Qty::num($group->sum(fn (PurchaseOrder $order) => $order->orderedQuantity())));
    }

    /**
     * @return Collection<string, float>
     */
    private function invoicedQtyGroupedBy(CarbonInterface $from, CarbonInterface $to, string $format): Collection
    {
        return PurchaseInvoice::query()
            ->with('lines')
            ->whereBetween('invoice_date', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->get()
            ->groupBy(fn (PurchaseInvoice $invoice) => $invoice->invoice_date?->format($format))
            ->map(fn ($group) => Qty::num($group->sum(fn (PurchaseInvoice $invoice) => $invoice->invoicedQuantity())));
    }

    /**
     * @return array<string, mixed>
     */
    public function dashboard(?int $partId, ?int $lotId, ?int $compareLotId, string $audience = 'master'): array
    {
        $orders = PurchaseOrder::query()
            ->with(['vendor', 'lines.part', 'lines.invoiceLines'])
            ->where('status', '!=', 'cancelled')
            ->orderByDesc('po_date')
            ->get();

        $lots = IncomingLot::query()->with(['part', 'vendor', 'reports'])->get();
        $receivedQty = Qty::num($lots->sum(fn (IncomingLot $lot) => (float) $lot->qty));
        $inspectedLots = $lots->filter(fn (IncomingLot $lot) => $lot->reports->isNotEmpty());
        $inspectedQty = Qty::num($inspectedLots->sum(fn (IncomingLot $lot) => (float) $lot->qty));
        $rejectedQty = Qty::num($lots->where('status', 'rejected')->sum(fn (IncomingLot $lot) => (float) $lot->qty));

        $orderedQty = Qty::num($orders->sum(fn (PurchaseOrder $order) => $order->orderedQuantity()));
        $invoicedQty = Qty::num($orders->sum(fn (PurchaseOrder $order) => $order->invoicedQuantity()));

        $openValue = 0.0;
        foreach ($orders as $order) {
            foreach ($order->lines as $line) {
                $openValue += $line->remainingQuantity() * (float) $line->unit_price;
            }
        }

        $parts = IncomingPart::query()->orderBy('part_number')->get();
        $partRows = $parts->map(function (IncomingPart $part) use ($lots, $orders) {
            $partLots = $lots->where('part_id', $part->id);
            $coming = Qty::num($partLots->sum(fn (IncomingLot $lot) => (float) $lot->qty));
            $inspected = Qty::num($partLots->filter(fn (IncomingLot $lot) => $lot->reports->isNotEmpty())->sum(fn (IncomingLot $lot) => (float) $lot->qty));
            $ordered = 0.0;
            $invoiced = 0.0;

            foreach ($orders as $order) {
                foreach ($order->lines->where('part_id', $part->id) as $line) {
                    $ordered += (float) $line->ordered_qty;
                    $invoiced += $line->invoicedQuantity();
                }
            }

            return [
                'id' => $part->id,
                'part_number' => $part->part_number,
                'name' => $part->name,
                'coming' => $coming,
                'inspected' => $inspected,
                'ordered' => Qty::num($ordered),
                'invoiced' => Qty::num($invoiced),
            ];
        })->filter(fn (array $row) => $row['ordered'] > 0 || $row['coming'] > 0)->values();

        $days = collect(range(13, 0))->map(fn (int $offset) => now()->subDays($offset)->startOfDay());
        $daily = [
            'labels' => $days->map(fn ($day) => $day->format('d M'))->all(),
            'datasets' => [
                [
                    'label' => 'Purchase orders',
                    'data' => $days->map(fn ($day) => PurchaseOrder::query()->whereDate('po_date', $day)->count())->all(),
                ],
                [
                    'label' => 'Purchase invoices',
                    'data' => $days->map(fn ($day) => PurchaseInvoice::query()->whereDate('invoice_date', $day)->count())->all(),
                ],
                [
                    'label' => 'Inspection reports',
                    'data' => $days->map(fn ($day) => InspectionReport::query()->whereDate('inspected_at', $day)->count())->all(),
                ],
            ],
        ];

        $activeParts = $partRows->take(8);
        $coverage = [
            'labels' => $activeParts->pluck('part_number')->all(),
            'datasets' => [
                ['label' => 'Qty received', 'data' => $activeParts->pluck('coming')->all()],
                ['label' => 'Qty inspected', 'data' => $activeParts->pluck('inspected')->all()],
            ],
        ];

        $poChartOrders = $orders->where('status', '!=', 'draft')->take(8);
        $poChart = [
            'labels' => $poChartOrders->pluck('po_number')->all(),
            'datasets' => [
                ['label' => 'Ordered', 'data' => $poChartOrders->map(fn (PurchaseOrder $order) => $order->orderedQuantity())->values()->all()],
                ['label' => 'Invoiced', 'data' => $poChartOrders->map(fn (PurchaseOrder $order) => $order->invoicedQuantity())->values()->all()],
            ],
        ];

        $vendorGroups = $lots->groupBy(fn (IncomingLot $lot) => $lot->vendor?->name ?? 'Unknown');
        $vendors = [
            'labels' => $vendorGroups->keys()->all(),
            'datasets' => [
                [
                    'label' => 'Accepted',
                    'data' => $vendorGroups->map(fn ($group) => Qty::num($group->where('status', 'accepted')->sum(fn (IncomingLot $lot) => (float) $lot->qty)))->values()->all(),
                ],
                [
                    'label' => 'Rejected',
                    'data' => $vendorGroups->map(fn ($group) => Qty::num($group->where('status', 'rejected')->sum(fn (IncomingLot $lot) => (float) $lot->qty)))->values()->all(),
                ],
                [
                    'label' => 'Conditional',
                    'data' => $vendorGroups->map(fn ($group) => Qty::num($group->where('status', 'partial')->sum(fn (IncomingLot $lot) => (float) $lot->qty)))->values()->all(),
                ],
                [
                    'label' => 'Waiting',
                    'data' => $vendorGroups->map(fn ($group) => Qty::num($group->where('status', 'received')->sum(fn (IncomingLot $lot) => (float) $lot->qty)))->values()->all(),
                ],
            ],
        ];

        $selectedPart = $parts->firstWhere('id', $partId) ?? $parts->first();
        $selectedLot = $lots->firstWhere('id', $lotId) ?? $lots->sortByDesc('received_at')->first();
        $compareLot = IncomingLot::query()
            ->with(['part', 'reports.results', 'reports.standard.characteristics', 'reports.samplingPlan'])
            ->find($compareLotId)
            ?? IncomingLot::query()->whereHas('reports')->with(['part', 'reports.results', 'reports.standard.characteristics', 'reports.samplingPlan'])->latest('received_at')->first();

        $tree = new TraceabilityTree;
        $commercial = $audience !== 'incoming';
        $inspection = $audience !== 'purchase';
        $emptyChart = ['labels' => [], 'datasets' => []];

        return [
            'as_of' => now()->format('d M Y H:i'),
            'received_qty' => $inspection ? $receivedQty : 0,
            'inspected_qty' => $inspection ? $inspectedQty : 0,
            'coverage' => $inspection && $receivedQty > 0 ? (int) round(($inspectedQty / $receivedQty) * 100) : 0,
            'rejected_qty' => $inspection ? $rejectedQty : 0,
            'rejection' => $inspection && $inspectedQty > 0 ? (int) round(($rejectedQty / $inspectedQty) * 100) : 0,
            'ordered_qty' => $commercial ? $orderedQty : 0,
            'invoiced_qty' => $commercial ? $invoicedQty : 0,
            'balance_qty' => $commercial ? max(0, Qty::num($orderedQty - $invoicedQty)) : 0,
            'open_value' => $commercial ? round($openValue, 2) : 0,
            'waiting' => $inspection ? $lots->filter(fn (IncomingLot $lot) => $lot->reports->isEmpty())->count() : 0,
            'overdue' => $commercial ? $orders->filter(fn (PurchaseOrder $order) => $order->isOverdue())->count() : 0,
            'part_rows' => $inspection
                ? $partRows->map(function (array $row) use ($commercial) {
                    if (! $commercial) {
                        $row['ordered'] = 0;
                        $row['invoiced'] = 0;
                    }

                    return $row;
                })->values()
                : collect(),
            'daily' => $audience === 'master' ? $daily : $emptyChart,
            'coverage_chart' => $inspection ? $coverage : $emptyChart,
            'po_chart' => $commercial ? $poChart : $emptyChart,
            'vendor_chart' => $inspection ? $vendors : $emptyChart,
            'parts' => $inspection ? $parts : collect(),
            'lots' => $inspection ? $lots->sortBy('lot_number')->values() : collect(),
            'selected_part_id' => $inspection ? $selectedPart?->id : null,
            'selected_lot_id' => $inspection ? $selectedLot?->id : null,
            'compare_lot_id' => $inspection ? $compareLot?->id : null,
            'part_tree' => $inspection && $selectedPart ? $tree->forPart($selectedPart, $commercial) : null,
            'lot_tree' => $inspection && $selectedLot ? $tree->forLot($selectedLot, $commercial) : null,
            'compare' => $inspection ? $this->compare($compareLot) : null,
            'balances' => $commercial ? $orders
                ->filter(fn (PurchaseOrder $order) => $order->status !== 'draft')
                ->map(fn (PurchaseOrder $order) => [
                    'po' => $order->po_number,
                    'vendor' => $order->vendor?->name,
                    'vendor_code' => $order->vendor?->code,
                    'pi' => $order->invoices->pluck('invoice_number')->implode(', '),
                    'status' => $order->status,
                    'ordered' => $order->orderedQuantity(),
                    'invoiced' => $order->invoicedQuantity(),
                    'balance' => $order->balanceQuantity(),
                    'overdue' => $order->isOverdue(),
                    'url' => route('admin::quality.purchase.orders.format', $order),
                ])
                ->values() : collect(),
        ];
    }

    public function getYtdStats(): array
    {
        $startOfYear = now()->startOfYear();

        return [
            'po_count' => PurchaseOrder::query()->where('po_date', '>=', $startOfYear)->count(),
            'inspection_count' => InspectionReport::query()->where('inspected_at', '>=', $startOfYear)->count(),
        ];
    }

    public function getMtdStats(): array
    {
        $startOfMonth = now()->startOfMonth();

        return [
            'po_count' => PurchaseOrder::query()->where('po_date', '>=', $startOfMonth)->count(),
            'inspection_count' => InspectionReport::query()->where('inspected_at', '>=', $startOfMonth)->count(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function compare(?IncomingLot $lot): ?array
    {
        if (! $lot) {
            return null;
        }

        $report = $lot->reports->sortByDesc('inspected_at')->first();
        $standard = $report?->standard ?? $lot->part?->currentStandard()?->load('characteristics');
        $results = $report?->results?->keyBy('inspection_characteristic_id') ?? collect();

        return [
            'lot' => $lot->lot_number,
            'part' => $lot->part?->label(),
            'qty' => Qty::format($lot->qty),
            'report' => $report?->report_number,
            'report_url' => $report ? route('admin::quality.incoming.reports.show', $report) : null,
            'result' => $report?->result,
            'standard' => $standard?->code,
            'plan' => $report?->samplingPlan?->code,
            'sample_size' => $report?->sample_size,
            'rows' => $standard
                ? $standard->characteristics->map(function ($characteristic) use ($results) {
                    $result = $results->get($characteristic->id);

                    return [
                        'name' => $characteristic->name,
                        'specification' => $characteristic->specification,
                        'critical' => $characteristic->is_critical,
                        'measured' => $result?->measured_value,
                        'result' => $result?->result,
                    ];
                })->all()
                : [],
        ];
    }
}
