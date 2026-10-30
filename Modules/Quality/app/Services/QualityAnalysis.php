<?php

namespace Modules\Quality\Services;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Modules\Quality\Models\ApprovedVendor;
use Modules\Quality\Models\IncomingLot;
use Modules\Quality\Models\IncomingPart;
use Modules\Quality\Models\InspectionReport;
use Modules\Quality\Models\PurchaseInvoice;
use Modules\Quality\Models\PurchaseInvoiceLine;
use Modules\Quality\Models\PurchaseOrder;
use Modules\Quality\Models\PurchaseOrderLine;
use Modules\Quality\Support\Qty;

class QualityAnalysis
{
    /**
     * Purchase graph within a date range.
     *
     * Period is current month (days), month wise, or yearly.
     *
     * @return array{type: string, title: string, dataset: array{labels: list<string>, datasets: list<array<string, mixed>>}}
     */
    public function purchaseGraph(string $period, string $from, string $to): array
    {
        [$fromDate, $toDate] = $this->normalizeRange($from, $to);

        return match ($period) {
            'month' => [
                'type' => 'line',
                'title' => __('Month wise'),
                'dataset' => $this->purchaseQtyByMonths($fromDate, $toDate),
            ],
            'year' => [
                'type' => 'bar',
                'title' => __('Yearly'),
                'dataset' => $this->purchaseQtyByYears($fromDate, $toDate),
            ],
            default => [
                'type' => 'bar',
                'title' => __('Current month'),
                'dataset' => $this->purchaseQtyByDays($fromDate, $toDate),
            ],
        };
    }

    /**
     * Incoming graph within a date range.
     *
     * Period is current month (days), month wise, or yearly.
     *
     * @return array{type: string, title: string, dataset: array{labels: list<string>, datasets: list<array<string, mixed>>}}
     */
    public function incomingGraph(string $period, string $from, string $to): array
    {
        [$fromDate, $toDate] = $this->normalizeRange($from, $to);

        return match ($period) {
            'month' => [
                'type' => 'line',
                'title' => __('Month wise'),
                'dataset' => $this->incomingQtyByMonths($fromDate, $toDate),
            ],
            'year' => [
                'type' => 'bar',
                'title' => __('Yearly'),
                'dataset' => $this->incomingQtyByYears($fromDate, $toDate),
            ],
            default => [
                'type' => 'bar',
                'title' => __('Current month'),
                'dataset' => $this->incomingQtyByDays($fromDate, $toDate),
            ],
        };
    }

    /**
     * The graph for one department, using the shared period and date range.
     *
     * @return array{type: string, title: string, subtitle: string, dataset: array{labels: list<string>, datasets: list<array<string, mixed>>}, show_values: bool, value_position: string, options: array<string, mixed>}|null
     */
    public function departmentGraph(string $department, string $period, string $from, string $to): ?array
    {
        if (! in_array($department, ['incoming', 'purchase'], true)) {
            return null;
        }

        if ($period === 'mtd') {
            return [
                'type' => 'bar',
                'title' => $department === 'incoming' ? __('Parts coming vs inspected') : __('PO Vs PI'),
                'subtitle' => $department === 'incoming'
                    ? __('Quantity received this year beside last year. Months are on the left, and the yearly totals are on the right.')
                    : __('Purchase orders this year beside last year. Months are on the left, and the yearly totals are on the right.'),
                'dataset' => $department === 'incoming' ? $this->incomingMtdYtdChart() : $this->mtdYtdChart(),
                'show_values' => true,
                'value_position' => 'above',
                'options' => ['plugins' => ['legend' => ['position' => 'bottom']]],
            ];
        }

        $axis = match ($period) {
            'month' => __('Month'),
            'year' => __('Year'),
            default => __('Day'),
        };

        if ($department === 'incoming') {
            $incoming = $this->incomingGraph($period, $from, $to);

            return [
                'type' => $incoming['type'],
                'title' => __('Parts coming vs inspected'),
                'subtitle' => __('Y-axis: Quantity, X-axis: :axis', ['axis' => $axis]),
                'dataset' => $incoming['dataset'],
                'show_values' => $incoming['type'] === 'bar',
                'value_position' => 'inside',
                'options' => [],
            ];
        }

        $purchase = $this->purchaseGraph($period, $from, $to);

        return [
            'type' => $purchase['type'],
            'title' => __('PO Vs PI'),
            'subtitle' => __('Y-axis: Quantity, X-axis: :axis', ['axis' => $axis]),
            'dataset' => $purchase['dataset'],
            'show_values' => $purchase['type'] === 'bar',
            'value_position' => 'inside',
            'options' => [],
        ];
    }

    /**
     * Purchase orders this year beside last year.
     *
     * Months run across the axis, then the two yearly totals, matching a grouped
     * comparison chart with the value drawn above each bar.
     *
     * @return array{labels: list<string>, datasets: list<array<string, mixed>>}
     */
    public function mtdYtdChart(): array
    {
        $currentYear = (int) now()->year;
        $previousYear = $currentYear - 1;
        $from = now()->subYear()->startOfYear();

        $counts = PurchaseOrder::query()
            ->where('po_date', '>=', $from)
            ->get(['po_date'])
            ->countBy(fn (PurchaseOrder $order) => $order->po_date?->format('Y-m') ?? '');

        $labels = [];
        $previous = [];
        $current = [];

        for ($month = 1; $month <= now()->month; $month++) {
            $labels[] = Carbon::create(now()->year, $month, 1)->format('M');
            $previous[] = (int) $counts->get(sprintf('%d-%02d', $previousYear, $month), 0);
            $current[] = (int) $counts->get(sprintf('%d-%02d', $currentYear, $month), 0);
        }

        $previousTotal = 0;
        $currentTotal = 0;
        for ($month = 1; $month <= 12; $month++) {
            $previousTotal += (int) $counts->get(sprintf('%d-%02d', $previousYear, $month), 0);
            if ($month <= now()->month) {
                $currentTotal += (int) $counts->get(sprintf('%d-%02d', $currentYear, $month), 0);
            }
        }

        $labels[] = (string) $previousYear;
        $labels[] = (string) $currentYear;
        $previous[] = $previousTotal;
        $previous[] = null;
        $current[] = null;
        $current[] = $currentTotal;

        return [
            'labels' => $labels,
            'datasets' => [
                ['label' => (string) $previousYear, 'data' => $previous, 'backgroundColor' => '#3b82f6'],
                ['label' => (string) $currentYear, 'data' => $current, 'backgroundColor' => '#f59e0b'],
            ],
        ];
    }

    /**
     * Quantity received this year beside last year, in the same month-then-year layout.
     *
     * @return array{labels: list<string>, datasets: list<array<string, mixed>>}
     */
    public function incomingMtdYtdChart(): array
    {
        $currentYear = (int) now()->year;
        $previousYear = $currentYear - 1;

        $totals = IncomingLot::query()
            ->where('received_at', '>=', now()->subYear()->startOfYear())
            ->get(['qty', 'received_at'])
            ->groupBy(fn (IncomingLot $lot) => $lot->received_at?->format('Y-m') ?? '')
            ->map(fn (Collection $lots) => Qty::num($lots->sum(fn (IncomingLot $lot) => (float) $lot->qty)));

        $labels = [];
        $previous = [];
        $current = [];

        for ($month = 1; $month <= now()->month; $month++) {
            $labels[] = Carbon::create(now()->year, $month, 1)->format('M');
            $previous[] = (float) $totals->get(sprintf('%d-%02d', $previousYear, $month), 0);
            $current[] = (float) $totals->get(sprintf('%d-%02d', $currentYear, $month), 0);
        }

        $previousTotal = 0.0;
        $currentTotal = 0.0;
        for ($month = 1; $month <= 12; $month++) {
            $previousTotal += (float) $totals->get(sprintf('%d-%02d', $previousYear, $month), 0);
            if ($month <= now()->month) {
                $currentTotal += (float) $totals->get(sprintf('%d-%02d', $currentYear, $month), 0);
            }
        }

        $labels[] = (string) $previousYear;
        $labels[] = (string) $currentYear;
        $previous[] = $previousTotal;
        $previous[] = null;
        $current[] = null;
        $current[] = $currentTotal;

        return [
            'labels' => $labels,
            'datasets' => [
                ['label' => (string) $previousYear, 'data' => $previous, 'backgroundColor' => '#3b82f6'],
                ['label' => (string) $currentYear, 'data' => $current, 'backgroundColor' => '#f59e0b'],
            ],
        ];
    }

    /**
     * Parts coming vs inspected, limited to lots received in the date range.
     *
     * @return array{labels: list<string>, datasets: list<array<string, mixed>>}
     */
    public function coverageChart(string $from, string $to): array
    {
        [$fromDate, $toDate] = $this->normalizeRange($from, $to);

        $lots = IncomingLot::query()
            ->with(['part', 'reports'])
            ->whereBetween('received_at', [$fromDate, $toDate])
            ->get();

        $rows = $lots
            ->groupBy('part_id')
            ->map(function (Collection $partLots) {
                /** @var IncomingLot $first */
                $first = $partLots->first();

                return [
                    'part_number' => $first->part?->part_number ?? __('Unknown'),
                    'coming' => Qty::num($partLots->sum(fn (IncomingLot $lot) => (float) $lot->qty)),
                    'inspected' => Qty::num($partLots->filter(fn (IncomingLot $lot) => $lot->reports->isNotEmpty())->sum(fn (IncomingLot $lot) => (float) $lot->qty)),
                ];
            })
            ->sortByDesc('coming')
            ->take(8)
            ->values();

        return [
            'labels' => $rows->pluck('part_number')->all(),
            'datasets' => [
                ['label' => 'Qty received', 'data' => $rows->pluck('coming')->all()],
                ['label' => 'Qty inspected', 'data' => $rows->pluck('inspected')->all()],
            ],
        ];
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
    private function incomingQtySeries(
        Collection $buckets,
        string $keyFormat,
        string $labelFormat,
        CarbonInterface $from,
        CarbonInterface $to,
    ): array {
        $received = $this->receivedQtyGroupedBy($from, $to, $keyFormat);
        $inspected = $this->inspectedQtyGroupedBy($from, $to, $keyFormat);

        return [
            'labels' => $buckets->map(fn ($bucket) => $bucket->format($labelFormat))->all(),
            'datasets' => [
                [
                    'label' => 'Qty received',
                    'data' => $buckets->map(fn ($bucket) => $received->get($bucket->format($keyFormat), 0.0))->all(),
                ],
                [
                    'label' => 'Qty inspected',
                    'data' => $buckets->map(fn ($bucket) => $inspected->get($bucket->format($keyFormat), 0.0))->all(),
                ],
            ],
        ];
    }

    /**
     * @return array{labels: list<string>, datasets: list<array<string, mixed>>}
     */
    private function incomingQtyByDays(CarbonInterface $from, CarbonInterface $to): array
    {
        $days = collect();
        for ($day = $from->copy()->startOfDay(); $day->lte($to->copy()->startOfDay()); $day->addDay()) {
            $days->push($day->copy());
        }

        return $this->incomingQtySeries($days, 'Y-m-d', 'd M', $from, $to);
    }

    /**
     * @return array{labels: list<string>, datasets: list<array<string, mixed>>}
     */
    private function incomingQtyByMonths(CarbonInterface $from, CarbonInterface $to): array
    {
        $months = collect();
        for ($month = $from->copy()->startOfMonth(); $month->lte($to->copy()->startOfMonth()); $month->addMonth()) {
            $months->push($month->copy());
        }

        return $this->incomingQtySeries($months, 'Y-m', 'M Y', $from, $to);
    }

    /**
     * @return array{labels: list<string>, datasets: list<array<string, mixed>>}
     */
    private function incomingQtyByYears(CarbonInterface $from, CarbonInterface $to): array
    {
        $years = collect();
        for ($year = $from->copy()->startOfYear(); $year->lte($to->copy()->startOfYear()); $year->addYear()) {
            $years->push($year->copy());
        }

        return $this->incomingQtySeries($years, 'Y', 'Y', $from, $to);
    }

    /**
     * @return Collection<string, float>
     */
    private function receivedQtyGroupedBy(CarbonInterface $from, CarbonInterface $to, string $format): Collection
    {
        return IncomingLot::query()
            ->whereBetween('received_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->get(['qty', 'received_at'])
            ->groupBy(fn (IncomingLot $lot) => $lot->received_at?->format($format) ?? '')
            ->map(fn (Collection $lots) => Qty::num($lots->sum(fn (IncomingLot $lot) => (float) $lot->qty)));
    }

    /**
     * @return Collection<string, float>
     */
    private function inspectedQtyGroupedBy(CarbonInterface $from, CarbonInterface $to, string $format): Collection
    {
        return IncomingLot::query()
            ->whereBetween('received_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->whereHas('reports')
            ->get(['qty', 'received_at'])
            ->groupBy(fn (IncomingLot $lot) => $lot->received_at?->format($format) ?? '')
            ->map(fn (Collection $lots) => Qty::num($lots->sum(fn (IncomingLot $lot) => (float) $lot->qty)));
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
    public function dashboard(?int $partId, ?int $lotId, string $audience = 'master'): array
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

        $activeParts = $partRows->take(8);
        $coverage = [
            'labels' => $activeParts->pluck('part_number')->all(),
            'datasets' => [
                ['label' => 'Qty received', 'data' => $activeParts->pluck('coming')->all()],
                ['label' => 'Qty inspected', 'data' => $activeParts->pluck('inspected')->all()],
            ],
        ];

        $selectedPart = $parts->firstWhere('id', $partId) ?? $parts->first();
        $selectedLot = $lots->firstWhere('id', $lotId) ?? $lots->sortByDesc('received_at')->first();

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
            'coverage_chart' => $inspection ? $coverage : $emptyChart,
            'parts' => $inspection ? $parts : collect(),
            'lots' => $inspection ? $lots->sortBy('lot_number')->values() : collect(),
            'selected_part_id' => $inspection ? $selectedPart?->id : null,
            'selected_lot_id' => $inspection ? $selectedLot?->id : null,
            'part_tree' => $inspection && $selectedPart ? $tree->forPart($selectedPart, $commercial) : null,
            'lot_tree' => $inspection && $selectedLot ? $tree->forLot($selectedLot, $commercial) : null,
        ];
    }

    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function paginatedPartRows(
        string $search = '',
        int $perPage = 10,
        string $pageName = 'partsPage',
        bool $commercial = true,
        string $sortBy = 'part',
        string $sortDirection = 'asc',
    ): LengthAwarePaginator {
        $query = IncomingPart::query()
            ->with(['vendor', 'vendors'])
            ->where(function (Builder $query) {
                $query->whereHas('lots')
                    ->orWhereHas('orderLines', function (Builder $lines) {
                        $lines->whereHas('purchaseOrder', fn (Builder $orders) => $orders->where('status', '!=', 'cancelled'));
                    });
            })
            ->when($search !== '', function (Builder $query) use ($search) {
                $term = '%'.$search.'%';
                $query->where(function (Builder $query) use ($term) {
                    $query->where('part_number', 'like', $term)
                        ->orWhere('name', 'like', $term);
                });
            });

        $this->applyPartSorting($query, $sortBy, $sortDirection);

        /** @var LengthAwarePaginator<int, IncomingPart> $paginator */
        $paginator = $query->paginate($perPage, pageName: $pageName);
        $stats = $this->partMovementStats($paginator->getCollection()->pluck('id')->all());

        return $paginator->through(function (IncomingPart $part) use ($stats, $commercial) {
            $row = $stats[$part->id] ?? [
                'coming' => 0.0,
                'inspected' => 0.0,
                'ordered' => 0.0,
                'invoiced' => 0.0,
            ];

            $vendors = $part->vendors;
            if ($vendors->isEmpty() && $part->vendor) {
                $vendors = collect([$part->vendor]);
            }

            return [
                'id' => $part->id,
                'part_number' => $part->part_number,
                'name' => $part->name,
                'vendors' => $vendors->map(fn (ApprovedVendor $vendor) => [
                    'name' => $vendor->name,
                    'code' => $vendor->code,
                ])->values()->all(),
                'coming' => $row['coming'],
                'inspected' => $row['inspected'],
                'ordered' => $commercial ? $row['ordered'] : 0.0,
                'invoiced' => $commercial ? $row['invoiced'] : 0.0,
            ];
        });
    }

    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function paginatedBalances(
        string $search = '',
        string $sortBy = 'po',
        string $sortDirection = 'asc',
        int $perPage = 10,
        string $pageName = 'balancesPage',
    ): LengthAwarePaginator {
        $sortDirection = $sortDirection === 'desc' ? 'desc' : 'asc';
        $sortable = ['po', 'vendor', 'status', 'ordered', 'invoiced', 'balance'];
        $sortBy = in_array($sortBy, $sortable, true) ? $sortBy : 'po';

        $query = PurchaseOrder::query()
            ->with(['vendor', 'invoices', 'lines.invoiceLines'])
            ->whereNotIn('status', ['draft', 'cancelled'])
            ->when($search !== '', function (Builder $query) use ($search) {
                $term = '%'.$search.'%';
                $query->where(function (Builder $query) use ($term) {
                    $query->where('po_number', 'like', $term)
                        ->orWhere('status', 'like', $term)
                        ->orWhereHas('vendor', function (Builder $vendor) use ($term) {
                            $vendor->where('name', 'like', $term)
                                ->orWhere('code', 'like', $term);
                        })
                        ->orWhereHas('invoices', fn (Builder $invoices) => $invoices->where('invoice_number', 'like', $term));
                });
            });

        $this->applyBalanceSorting($query, $sortBy, $sortDirection);

        /** @var LengthAwarePaginator<int, PurchaseOrder> $paginator */
        $paginator = $query->paginate($perPage, pageName: $pageName);

        return $paginator->through(fn (PurchaseOrder $order) => [
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
        ]);
    }

    /**
     * @param  list<int|string>  $partIds
     * @return array<int, array{coming: float, inspected: float, ordered: float, invoiced: float}>
     */
    private function partMovementStats(array $partIds): array
    {
        if ($partIds === []) {
            return [];
        }

        $coming = IncomingLot::query()
            ->whereIn('part_id', $partIds)
            ->selectRaw('part_id, coalesce(sum(qty), 0) as total')
            ->groupBy('part_id')
            ->pluck('total', 'part_id');

        $inspected = IncomingLot::query()
            ->whereIn('part_id', $partIds)
            ->whereHas('reports')
            ->selectRaw('part_id, coalesce(sum(qty), 0) as total')
            ->groupBy('part_id')
            ->pluck('total', 'part_id');

        $ordered = PurchaseOrderLine::query()
            ->whereIn('part_id', $partIds)
            ->whereHas('purchaseOrder', fn (Builder $orders) => $orders->where('status', '!=', 'cancelled'))
            ->selectRaw('part_id, coalesce(sum(ordered_qty), 0) as total')
            ->groupBy('part_id')
            ->pluck('total', 'part_id');

        $invoiced = PurchaseInvoiceLine::query()
            ->whereIn('part_id', $partIds)
            ->whereHas('purchaseOrderLine.purchaseOrder', fn (Builder $orders) => $orders->where('status', '!=', 'cancelled'))
            ->selectRaw('part_id, coalesce(sum(invoiced_qty), 0) as total')
            ->groupBy('part_id')
            ->pluck('total', 'part_id');

        $stats = [];

        foreach ($partIds as $partId) {
            $id = (int) $partId;
            $stats[$id] = [
                'coming' => Qty::num($coming->get($id, 0)),
                'inspected' => Qty::num($inspected->get($id, 0)),
                'ordered' => Qty::num($ordered->get($id, 0)),
                'invoiced' => Qty::num($invoiced->get($id, 0)),
            ];
        }

        return $stats;
    }

    /**
     * @param  Builder<IncomingPart>  $query
     */
    private function applyPartSorting(Builder $query, string $sortBy, string $sortDirection): void
    {
        $sortDirection = $sortDirection === 'desc' ? 'desc' : 'asc';
        $sortBy = in_array($sortBy, ['part', 'vendor', 'ordered', 'invoiced', 'received', 'inspected', 'gap'], true)
            ? $sortBy
            : 'part';

        match ($sortBy) {
            'vendor' => $query->orderBy(
                ApprovedVendor::query()
                    ->select('approved_vendors.name')
                    ->join('incoming_part_vendor', 'incoming_part_vendor.approved_vendor_id', '=', 'approved_vendors.id')
                    ->whereColumn('incoming_part_vendor.incoming_part_id', 'incoming_parts.id')
                    ->orderBy('approved_vendors.name')
                    ->limit(1),
                $sortDirection
            )->orderBy('part_number'),
            'ordered' => $query->orderBy(
                PurchaseOrderLine::query()
                    ->selectRaw('coalesce(sum(purchase_order_lines.ordered_qty), 0)')
                    ->join('purchase_orders', 'purchase_orders.id', '=', 'purchase_order_lines.purchase_order_id')
                    ->whereColumn('purchase_order_lines.part_id', 'incoming_parts.id')
                    ->where('purchase_orders.status', '!=', 'cancelled'),
                $sortDirection
            )->orderBy('part_number'),
            'invoiced' => $query->orderBy(
                PurchaseInvoiceLine::query()
                    ->selectRaw('coalesce(sum(purchase_invoice_lines.invoiced_qty), 0)')
                    ->join('purchase_order_lines', 'purchase_order_lines.id', '=', 'purchase_invoice_lines.purchase_order_line_id')
                    ->join('purchase_orders', 'purchase_orders.id', '=', 'purchase_order_lines.purchase_order_id')
                    ->whereColumn('purchase_invoice_lines.part_id', 'incoming_parts.id')
                    ->where('purchase_orders.status', '!=', 'cancelled'),
                $sortDirection
            )->orderBy('part_number'),
            'received' => $query->orderBy(
                IncomingLot::query()
                    ->selectRaw('coalesce(sum(qty), 0)')
                    ->whereColumn('incoming_lots.part_id', 'incoming_parts.id'),
                $sortDirection
            )->orderBy('part_number'),
            'inspected' => $query->orderBy(
                IncomingLot::query()
                    ->selectRaw('coalesce(sum(qty), 0)')
                    ->whereColumn('incoming_lots.part_id', 'incoming_parts.id')
                    ->whereHas('reports'),
                $sortDirection
            )->orderBy('part_number'),
            'gap' => $query->orderByRaw(
                'CASE WHEN (
                    coalesce((select sum(qty) from incoming_lots where part_id = incoming_parts.id), 0)
                    - coalesce((select sum(qty) from incoming_lots where part_id = incoming_parts.id and exists (
                        select 1 from inspection_reports where inspection_reports.incoming_lot_id = incoming_lots.id
                    )), 0)
                ) < 0 THEN 0 ELSE (
                    coalesce((select sum(qty) from incoming_lots where part_id = incoming_parts.id), 0)
                    - coalesce((select sum(qty) from incoming_lots where part_id = incoming_parts.id and exists (
                        select 1 from inspection_reports where inspection_reports.incoming_lot_id = incoming_lots.id
                    )), 0)
                ) END '.$sortDirection
            )->orderBy('part_number'),
            default => $query->orderBy('part_number', $sortDirection),
        };
    }

    /**
     * @param  Builder<PurchaseOrder>  $query
     */
    private function applyBalanceSorting(Builder $query, string $sortBy, string $sortDirection): void
    {
        match ($sortBy) {
            'vendor' => $query->orderBy(
                ApprovedVendor::query()
                    ->select('name')
                    ->whereColumn('approved_vendors.id', 'purchase_orders.vendor_id')
                    ->limit(1),
                $sortDirection
            )->orderBy('po_number'),
            'status' => $query->orderBy('status', $sortDirection)->orderBy('po_number'),
            'ordered' => $query->orderBy(
                PurchaseOrderLine::query()
                    ->selectRaw('coalesce(sum(ordered_qty), 0)')
                    ->whereColumn('purchase_order_id', 'purchase_orders.id'),
                $sortDirection
            )->orderBy('po_number'),
            'invoiced' => $query->orderBy(
                PurchaseInvoiceLine::query()
                    ->selectRaw('coalesce(sum(purchase_invoice_lines.invoiced_qty), 0)')
                    ->join('purchase_order_lines', 'purchase_order_lines.id', '=', 'purchase_invoice_lines.purchase_order_line_id')
                    ->whereColumn('purchase_order_lines.purchase_order_id', 'purchase_orders.id'),
                $sortDirection
            )->orderBy('po_number'),
            'balance' => $query->orderByRaw(
                '(coalesce((select sum(ordered_qty) from purchase_order_lines where purchase_order_id = purchase_orders.id), 0)
                - coalesce((select sum(purchase_invoice_lines.invoiced_qty) from purchase_invoice_lines
                    inner join purchase_order_lines on purchase_order_lines.id = purchase_invoice_lines.purchase_order_line_id
                    where purchase_order_lines.purchase_order_id = purchase_orders.id), 0)) '.$sortDirection
            )->orderBy('po_number'),
            default => $query->orderBy('po_number', $sortDirection),
        };
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
}
