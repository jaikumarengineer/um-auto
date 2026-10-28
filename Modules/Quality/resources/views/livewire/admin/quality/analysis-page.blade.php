<?php

use App\Concerns\WithSorting;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Modules\Quality\Models\IncomingLot;
use Modules\Quality\Models\IncomingPart;
use Modules\Quality\Services\QualityAnalysis;
use Modules\Quality\Support\Qty;

new #[Layout('core::layouts.app')] class extends Component {
    use WithSorting;

    public string $traceMode = 'part';

    public ?int $partId = null;

    public ?int $lotId = null;

    public ?int $compareLotId = null;

    /** @var 'day'|'week'|'month'|'year' */
    public string $graphPeriod = 'day';

    /** Flatpickr range value, e.g. "2026-01-01 to 2026-01-31". */
    public string $graphDateRange = '';

    public string $sortBy = 'po';

    public string $sortDirection = 'asc';

    protected array $sortable = ['po', 'vendor', 'status', 'ordered', 'invoiced', 'balance'];

    public function mount(): void
    {
        $this->partId = IncomingPart::query()->orderBy('part_number')->value('id');
        $this->lotId = IncomingLot::query()->latest('received_at')->value('id');
        $this->compareLotId = IncomingLot::query()->whereHas('reports')->latest('received_at')->value('id');
        $this->applyPeriodDefaults('day');
    }

    public function updatedGraphPeriod(string $period): void
    {
        if (! in_array($period, ['day', 'week', 'month', 'year'], true)) {
            $this->graphPeriod = 'day';
            $period = 'day';
        }

        $this->applyPeriodDefaults($period);
    }

    protected function applyPeriodDefaults(string $period): void
    {
        [$from, $to] = match ($period) {
            'week' => [now()->subWeeks(11)->startOfWeek(), now()],
            'month' => [now()->subMonths(11)->startOfMonth(), now()],
            'year' => [now()->subYears(2)->startOfYear(), now()],
            default => [now()->subDays(29), now()],
        };

        $this->graphDateRange = $from->toDateString().' to '.$to->toDateString();
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function graphRangeDates(): array
    {
        $parts = preg_split('/\s+to\s+/i', trim($this->graphDateRange)) ?: [];
        $from = $parts[0] ?? '';
        $to = $parts[1] ?? $parts[0] ?? '';

        if ($from === '' || ! strtotime($from)) {
            $from = now()->subDays(29)->toDateString();
        }
        if ($to === '' || ! strtotime($to)) {
            $to = now()->toDateString();
        }

        return [$from, $to];
    }

    public function audience(): string
    {
        return auth()->user()?->qualityAudience() ?? 'master';
    }

    public function with(QualityAnalysis $analysis): array
    {
        $dashboard = $analysis->dashboard($this->partId, $this->lotId, $this->compareLotId, $this->audience());
        $balances = collect($dashboard['balances'] ?? []);

        $sortBy = in_array($this->sortBy, $this->sortable, true) ? $this->sortBy : 'po';
        $balances = ($this->sortDirection === 'desc'
            ? $balances->sortByDesc($sortBy)
            : $balances->sortBy($sortBy)
        )->values();

        [$from, $to] = $this->graphRangeDates();
        $purchaseGraph = $analysis->purchaseGraph($this->graphPeriod, $from, $to);

        return array_merge($dashboard, [
            'audience' => $this->audience(),
            'balances' => $balances,
            'purchase_graph' => $purchaseGraph,
            'monthly_chart' => $analysis->getMonthlyLineChart(),
            'yearly_chart' => $analysis->getYearlyBarChart(),
            'mtd' => $analysis->getMtdStats(),
            'ytd' => $analysis->getYtdStats(),
        ]);
    }
}; ?>

<div class="space-y-6">
    <x-core::ui.page-header
        :title="match ($audience) {
            'purchase' => __('Purchase analysis'),
            'incoming' => __('Incoming analysis'),
            default => __('Quality analysis'),
        }"
        :subtitle="__('As of :time.', ['time' => $as_of])"
        :breadcrumbs="[
            admin_breadcrumb_home(),
            ['label' => __('Analysis')],
        ]"
    />

    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @if (in_array($audience, ['master', 'incoming'], true))
            <div class="admin-card p-5">
                <p class="text-sm text-zinc-500">{{ __('Received vs inspected') }}</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums">{{ Qty::format($received_qty) }} / {{ Qty::format($inspected_qty) }}</p>
                <p class="mt-1 text-xs text-zinc-500">{{ __(':percent% coverage', ['percent' => $coverage]) }}</p>
            </div>
            <div class="admin-card p-5">
                <p class="text-sm text-zinc-500">{{ __('Rejection') }}</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums">{{ $rejection }}%</p>
                <p class="mt-1 text-xs text-zinc-500">{{ __(':qty rejected · :waiting waiting', ['qty' => Qty::format($rejected_qty), 'waiting' => $waiting]) }}</p>
            </div>
        @endif
        @if (in_array($audience, ['master', 'purchase'], true))
            <div class="admin-card p-5">
                <p class="text-sm text-zinc-500">{{ __('PO vs PI qty') }}</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums">{{ Qty::format($ordered_qty) }} / {{ Qty::format($invoiced_qty) }}</p>
                <p class="mt-1 text-xs text-zinc-500">{{ __('Balance :qty', ['qty' => Qty::format($balance_qty)]) }}</p>
            </div>
            <div class="admin-card p-5">
                <p class="text-sm text-zinc-500">{{ __('Open PO value') }}</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums">₹{{ Qty::money($open_value) }}</p>
                <p class="mt-1 text-xs text-zinc-500">{{ __(':count overdue', ['count' => $overdue]) }}</p>
            </div>
        @endif
        @if ($audience === 'master')
            <div class="admin-card p-5">
                <p class="text-sm text-zinc-500">{{ __('MTD (PO / Inspection)') }}</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums">{{ $mtd['po_count'] }} / {{ $mtd['inspection_count'] }}</p>
            </div>
            <div class="admin-card p-5">
                <p class="text-sm text-zinc-500">{{ __('YTD (PO / Inspection)') }}</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums">{{ $ytd['po_count'] }} / {{ $ytd['inspection_count'] }}</p>
            </div>
        @endif
    </section>

    <section class="grid gap-4 xl:grid-cols-2">
        @if (in_array($audience, ['master', 'incoming'], true))
        <div class="admin-card p-4">
            <flux:heading>{{ __('Parts coming vs inspected') }}</flux:heading>
            <div class="mt-4">
                <x-core::charts.chart type="bar" :dataset="$coverage_chart" :height="280" />
            </div>
        </div>
        <div class="admin-card p-4">
            <flux:heading>{{ __('Vendor quality') }}</flux:heading>
            <div class="mt-4">
                <x-core::charts.chart type="bar" :dataset="$vendor_chart" :height="280" />
            </div>
        </div>
        @endif
        @if (in_array($audience, ['master', 'purchase'], true))
        <div class="admin-card p-4 {{ $audience === 'purchase' ? 'xl:col-span-2' : '' }}">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <flux:heading>{{ __('Vendor Wise PO Vs PI') }}</flux:heading>
                    <p class="mt-1 text-sm text-zinc-500">{{ __('Y-axis: Quantity, X-axis: ' . ($graphPeriod === 'day' ? __('Vendor') : __('Time'))) }}</p>
                </div>
                <div class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row sm:items-end sm:justify-end">
                    <div class="sm:w-40">
                        <flux:select wire:model.live="graphPeriod" :label="__('Period')">
                            <flux:select.option value="day">{{ __('Vendor wise') }}</flux:select.option>
                            <flux:select.option value="week">{{ __('Weekly') }}</flux:select.option>
                            <flux:select.option value="month">{{ __('Monthwise') }}</flux:select.option>
                            <flux:select.option value="year">{{ __('Year wise') }}</flux:select.option>
                        </flux:select>
                    </div>
                    <div class="sm:w-64" wire:key="graph-range-picker-{{ $graphPeriod }}">
                        <x-core::forms.flatpickr
                            wire:model="graphDateRange"
                            mode="range"
                            :label="__('Date range')"
                            :placeholder="__('Select date range…')"
                        />
                    </div>
                </div>
            </div>
            <div class="mt-4" wire:key="purchase-graph-{{ $graphPeriod }}-{{ $graphDateRange }}">
                <x-core::charts.chart
                    :type="$purchase_graph['type']"
                    :dataset="$purchase_graph['dataset']"
                    :height="280"
                />
            </div>
        </div>
        @endif
        @if ($audience === 'master')
        <div class="admin-card p-4">
            <flux:heading>{{ __('Last 14 days') }}</flux:heading>
            <div class="mt-4">
                <x-core::charts.chart type="line" :dataset="$daily" :height="280" />
            </div>
        </div>
        <div class="admin-card p-4">
            <flux:heading>{{ __('Monthly trend') }}</flux:heading>
            <div class="mt-4">
                <x-core::charts.chart type="line" :dataset="$monthly_chart" :height="280" />
            </div>
        </div>
        <div class="admin-card p-4">
            <flux:heading>{{ __('Yearly trend') }}</flux:heading>
            <div class="mt-4">
                <x-core::charts.chart type="bar" :dataset="$yearly_chart" :height="280" />
            </div>
        </div>
        @endif
    </section>

    @if (in_array($audience, ['master', 'incoming'], true))
    <section class="overflow-x-auto admin-card">
        <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
            <thead class="bg-[var(--admin-surface-muted)]">
                <tr>
                    @foreach (array_filter([
                        __('Part'),
                        $audience === 'master' ? __('Ordered') : null,
                        $audience === 'master' ? __('Invoiced') : null,
                        __('Received'),
                        __('Inspected'),
                        __('Gap'),
                    ]) as $heading)
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500">{{ $heading }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse ($part_rows as $row)
                    <tr>
                        <td class="px-4 py-3 text-sm"><span class="font-medium">{{ $row['part_number'] }}</span><br><span class="text-xs text-zinc-500">{{ $row['name'] }}</span></td>
                        @if ($audience === 'master')
                            <td class="px-4 py-3 text-sm tabular-nums">{{ Qty::format($row['ordered']) }}</td>
                            <td class="px-4 py-3 text-sm tabular-nums">{{ Qty::format($row['invoiced']) }}</td>
                        @endif
                        <td class="px-4 py-3 text-sm tabular-nums">{{ Qty::format($row['coming']) }}</td>
                        <td class="px-4 py-3 text-sm tabular-nums">{{ Qty::format($row['inspected']) }}</td>
                        <td class="px-4 py-3 text-sm tabular-nums">{{ Qty::format(max(0, $row['coming'] - $row['inspected'])) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="{{ $audience === 'master' ? 6 : 4 }}" class="px-4 py-8 text-center text-sm text-zinc-500">{{ __('No part movement yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>
    @endif

    @if (in_array($audience, ['master', 'purchase'], true))
    <section class="overflow-x-auto admin-card">
        <div class="border-b border-zinc-200 px-4 py-3 dark:border-zinc-700">
            <flux:heading>{{ __('Purchase order balance') }}</flux:heading>
        </div>
        <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
            <thead>
                <tr>
                    <x-core::ui.sortable-th column="po" :label="__('PO / PI')" :sort-by="$sortBy" :sort-direction="$sortDirection" />
                    <x-core::ui.sortable-th column="vendor" :label="__('Vendor')" :sort-by="$sortBy" :sort-direction="$sortDirection" />
                    <x-core::ui.sortable-th column="status" :label="__('Status')" :sort-by="$sortBy" :sort-direction="$sortDirection" />
                    <x-core::ui.sortable-th column="ordered" :label="__('Ordered')" :sort-by="$sortBy" :sort-direction="$sortDirection" />
                    <x-core::ui.sortable-th column="invoiced" :label="__('Invoiced')" :sort-by="$sortBy" :sort-direction="$sortDirection" />
                    <x-core::ui.sortable-th column="balance" :label="__('Balance')" :sort-by="$sortBy" :sort-direction="$sortDirection" />
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse ($balances as $row)
                    <tr wire:key="balance-{{ $row['po'] }}">
                        <td class="px-4 py-3 text-sm">
                            <a class="text-primary hover:underline" href="{{ $row['url'] }}">{{ $row['po'] }}</a>
                            @if ($row['pi'])
                                <br><span class="text-xs text-zinc-500">{{ $row['pi'] }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-sm">
                            {{ $row['vendor'] }}
                            @if ($row['vendor_code'])
                                <br><span class="text-xs text-zinc-500">{{ $row['vendor_code'] }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3"><x-quality::status-pill :status="$row['status']" /> @if ($row['overdue']) <span class="ms-1 text-xs text-red-600">{{ __('Overdue') }}</span> @endif</td>
                        <td class="px-4 py-3 text-sm tabular-nums">{{ Qty::format($row['ordered']) }}</td>
                        <td class="px-4 py-3 text-sm tabular-nums">{{ Qty::format($row['invoiced']) }}</td>
                        <td class="px-4 py-3 text-sm tabular-nums">{{ Qty::format($row['balance']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-sm text-zinc-500">{{ __('No purchase orders yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>
    @endif

    @if (in_array($audience, ['master', 'incoming'], true))
    <section class="grid gap-4 xl:grid-cols-2">
        <div class="admin-card p-4">
            <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <flux:heading>{{ __('History tree') }}</flux:heading>
                    <p class="text-sm text-zinc-500">{{ $audience === 'incoming' ? __('Part, batch, and inspection report.') : __('Part to purchase order, invoice, batch, and report. Or start from a batch.') }}</p>
                </div>
                <div class="flex gap-2">
                    <flux:button size="sm" :variant="$traceMode === 'part' ? 'primary' : 'ghost'" wire:click="$set('traceMode', 'part')">{{ __('Part') }}</flux:button>
                    <flux:button size="sm" :variant="$traceMode === 'lot' ? 'primary' : 'ghost'" wire:click="$set('traceMode', 'lot')">{{ __('Batch') }}</flux:button>
                </div>
            </div>
            @if ($traceMode === 'part')
                <flux:select wire:model.live="partId" :label="__('Part')">
                    @foreach ($parts as $part)
                        <flux:select.option :value="$part->id">{{ $part->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
                <div class="mt-4">
                    @if ($part_tree)
                        <x-quality::trace-node :node="$part_tree" />
                    @endif
                </div>
            @else
                <flux:select wire:model.live="lotId" :label="__('Batch')">
                    @foreach ($lots as $lot)
                        <flux:select.option :value="$lot->id">{{ $lot->lot_number }} · {{ $lot->part?->part_number }}</flux:select.option>
                    @endforeach
                </flux:select>
                <div class="mt-4">
                    @if ($lot_tree)
                        <x-quality::trace-node :node="$lot_tree" />
                    @endif
                </div>
            @endif
        </div>

        <div class="admin-card p-4">
            <flux:heading>{{ __('Standard vs report') }}</flux:heading>
            <p class="mb-4 text-sm text-zinc-500">{{ __('The inspection standard beside the latest measurements for one batch.') }}</p>
            <flux:select wire:model.live="compareLotId" :label="__('Batch')">
                @foreach ($lots as $lot)
                    <flux:select.option :value="$lot->id">{{ $lot->lot_number }} · {{ $lot->part?->part_number }}</flux:select.option>
                @endforeach
            </flux:select>

            @if ($compare)
                <div class="mt-4 flex flex-wrap items-center gap-2 text-sm">
                    <span class="font-medium">{{ $compare['lot'] }}</span>
                    <span class="text-zinc-500">{{ $compare['part'] }} · {{ $compare['qty'] }}</span>
                    @if ($compare['result'])
                        <x-quality::status-pill :status="$compare['result']" />
                    @endif
                    @if ($compare['report_url'])
                        <a class="text-primary hover:underline" href="{{ $compare['report_url'] }}" wire:navigate>{{ $compare['report'] }}</a>
                    @else
                        <span class="text-zinc-500">{{ __('Not inspected') }}</span>
                    @endif
                </div>
                <p class="mt-2 text-xs text-zinc-500">{{ $compare['standard'] ?: __('No standard') }} @if ($compare['plan']) · {{ $compare['plan'] }} · {{ __('sample') }} {{ $compare['sample_size'] }} @endif</p>
                <div class="mt-3 overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs uppercase tracking-wider text-zinc-500">
                                <th class="py-2 pe-3">{{ __('Check') }}</th>
                                <th class="py-2 pe-3">{{ __('Standard') }}</th>
                                <th class="py-2 pe-3">{{ __('Measured') }}</th>
                                <th class="py-2">{{ __('Result') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($compare['rows'] as $row)
                                <tr class="border-t border-zinc-200 dark:border-zinc-700">
                                    <td class="py-2 pe-3">{{ $row['name'] }} @if ($row['critical']) <span class="text-xs text-red-600">{{ __('Critical') }}</span> @endif</td>
                                    <td class="py-2 pe-3 text-zinc-500">{{ $row['specification'] }}</td>
                                    <td class="py-2 pe-3">{{ $row['measured'] ?: '—' }}</td>
                                    <td class="py-2">@if ($row['result']) <x-quality::status-pill :status="$row['result']" /> @else — @endif</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="py-4 text-zinc-500">{{ __('No standard is active for this part.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </section>
    @endif
</div>
