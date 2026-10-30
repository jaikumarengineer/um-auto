<?php

use App\Concerns\WithSorting;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Modules\Quality\Models\IncomingLot;
use Modules\Quality\Models\IncomingPart;
use Modules\Quality\Services\QualityAnalysis;
use Modules\Quality\Support\QualityAlertCatalog;
use Modules\Quality\Support\Qty;

new #[Layout('core::layouts.app')] class extends Component {
    use WithPagination;
    use WithSorting;

    public string $traceMode = 'part';

    public ?int $partId = null;

    public ?int $lotId = null;

    /** Incoming, purchase, or a later department from the catalog. */
    public string $graphDepartment = 'incoming';

    /** @var 'current'|'month'|'year'|'mtd' */
    public string $graphPeriod = 'current';

    /** Flatpickr range value, e.g. "2026-01-01 to 2026-01-31". */
    public string $graphDateRange = '';

    public string $sortBy = 'po';

    public string $sortDirection = 'asc';

    public string $partSortBy = 'part';

    public string $partSortDirection = 'asc';

    public string $partSearch = '';

    public string $balanceSearch = '';

    public int $perPage = 10;

    protected array $sortable = ['po', 'vendor', 'status', 'ordered', 'invoiced', 'balance'];

    protected string $sortPaginationPageName = 'balancesPage';

    public function mount(): void
    {
        $this->partId = IncomingPart::query()->orderBy('part_number')->value('id');
        $this->lotId = IncomingLot::query()->latest('received_at')->value('id');
        $this->applyPeriodDefaults('current');
    }

    public function updatedGraphDepartment(string $department): void
    {
        if (! array_key_exists($department, QualityAlertCatalog::DEPARTMENTS)) {
            $this->graphDepartment = 'incoming';
        }
    }

    public function sortParts(string $column): void
    {
        $allowed = ['part', 'vendor', 'ordered', 'invoiced', 'received', 'inspected', 'gap'];

        if (! in_array($column, $allowed, true)) {
            return;
        }

        if ($this->partSortBy === $column) {
            $this->partSortDirection = $this->partSortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->partSortBy = $column;
            $this->partSortDirection = 'asc';
        }

        $this->resetPage('partsPage');
    }

    public function analysisTitle(string $audience): string
    {
        $department = $audience === 'master' ? $this->graphDepartment : $audience;

        return match ($department) {
            'purchase' => __('Purchase Department'),
            'incoming' => __('Incoming Department'),
            default => __('Quality analysis'),
        };
    }

    public function updatingPartSearch(): void
    {
        $this->resetPage('partsPage');
    }

    public function updatingBalanceSearch(): void
    {
        $this->resetPage('balancesPage');
    }

    public function updatingPerPage(): void
    {
        $this->resetPage('partsPage');
        $this->resetPage('balancesPage');
    }

    public function updatedPerPage(): void
    {
        if (! in_array($this->perPage, [10, 25, 50], true)) {
            $this->perPage = 10;
        }
    }

    public function updatedGraphPeriod(string $period): void
    {
        if (! in_array($period, ['current', 'month', 'year', 'mtd'], true)) {
            $this->graphPeriod = 'current';
            $period = 'current';
        }

        $this->applyPeriodDefaults($period);
    }

    protected function applyPeriodDefaults(string $period): void
    {
        [$from, $to] = match ($period) {
            'month' => [now()->subMonths(11)->startOfMonth(), now()],
            'year' => [now()->subYears(2)->startOfYear(), now()],
            'mtd' => [now()->subYear()->startOfYear(), now()],
            default => [now()->startOfMonth(), now()],
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
            $from = now()->startOfMonth()->toDateString();
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
        $audience = $this->audience();
        $dashboard = $analysis->dashboard($this->partId, $this->lotId, $audience);
        $inspection = in_array($audience, ['master', 'incoming'], true);
        $commercial = in_array($audience, ['master', 'purchase'], true);

        $partRows = $inspection
            ? $analysis->paginatedPartRows($this->partSearch, $this->perPage, 'partsPage', $audience === 'master', $this->partSortBy, $this->partSortDirection)
            : new LengthAwarePaginator([], 0, $this->perPage, 1, ['pageName' => 'partsPage', 'path' => request()->url()]);

        $balances = $commercial
            ? $analysis->paginatedBalances($this->balanceSearch, $this->sortBy, $this->sortDirection, $this->perPage, 'balancesPage')
            : new LengthAwarePaginator([], 0, $this->perPage, 1, ['pageName' => 'balancesPage', 'path' => request()->url()]);

        [$from, $to] = $this->graphRangeDates();
        $graphView = $audience === 'master' ? $this->graphDepartment : $audience;

        return array_merge($dashboard, [
            'audience' => $audience,
            'departments' => QualityAlertCatalog::departmentLabels(),
            'part_rows' => $partRows,
            'balances' => $balances,
            'department_graph' => $analysis->departmentGraph($graphView, $this->graphPeriod, $from, $to),
            'page_title' => $this->analysisTitle($audience),
            'mtd' => $analysis->getMtdStats(),
            'ytd' => $analysis->getYtdStats(),
        ]);
    }
}; ?>

<div class="space-y-6">
    <x-core::ui.page-header
        :title="$page_title"
        :subtitle="__('As of :time.', ['time' => $as_of])"
        :breadcrumbs="[
            admin_breadcrumb_home(),
            ['label' => __('Analysis')],
        ]"
    >
        <x-slot:actions>
            <div class="w-48">
                <flux:select wire:model.live="graphPeriod" :label="__('Period')">
                    <flux:select.option value="current">{{ __('Current month') }}</flux:select.option>
                    <flux:select.option value="month">{{ __('Month wise') }}</flux:select.option>
                    <flux:select.option value="year">{{ __('Yearly') }}</flux:select.option>
                    <flux:select.option value="mtd">{{ __('MTD / YTD') }}</flux:select.option>
                </flux:select>
            </div>
            <div class="w-64" wire:key="graph-range-picker-{{ $graphPeriod }}">
                <x-core::forms.flatpickr
                    wire:model="graphDateRange"
                    mode="range"
                    :label="__('Date range')"
                    :placeholder="__('Select date range…')"
                />
            </div>
            @if ($audience === 'master')
                <div class="w-44">
                    <flux:select wire:model.live="graphDepartment" :label="__('Department')">
                        @foreach ($departments as $key => $label)
                            <flux:select.option :value="$key">{{ $label }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
            @endif
        </x-slot:actions>
    </x-core::ui.page-header>

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

    @php
        $graphView = $audience === 'master' ? $graphDepartment : $audience;
    @endphp

    <section>
        @if ($department_graph)
        <div class="admin-card p-4">
            <flux:heading>{{ $department_graph['title'] }}</flux:heading>
            @if ($department_graph['subtitle'] !== '')
                <p class="mt-1 text-sm text-zinc-500">{{ $department_graph['subtitle'] }}</p>
            @endif
            <div class="mt-4" wire:key="department-graph-{{ $graphView }}-{{ $graphPeriod }}-{{ $graphDateRange }}">
                <x-core::charts.chart
                    :type="$department_graph['type']"
                    :dataset="$department_graph['dataset']"
                    :height="320"
                    :show-values="$department_graph['show_values']"
                    :value-position="$department_graph['value_position']"
                    :options="$department_graph['options']"
                />
            </div>
        </div>
        @elseif ($audience === 'master')
        <div class="admin-card p-4">
            <flux:heading>{{ $departments[$graphDepartment] ?? __('Department') }}</flux:heading>
            <p class="mt-2 text-sm text-zinc-500">{{ __('No graph is set up for this department yet.') }}</p>
        </div>
        @endif
    </section>

    @if (in_array($audience, ['master', 'incoming'], true))
    <section class="admin-card">
        <div class="border-b border-zinc-200 px-4 py-3 dark:border-zinc-700">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <flux:heading>{{ __('Parts movement') }}</flux:heading>
                <div class="flex w-full flex-col gap-3 sm:w-auto sm:flex-row sm:items-center">
                    <div class="w-full sm:w-72">
                        <flux:input
                            wire:model.live.debounce.300ms="partSearch"
                            icon="magnifying-glass"
                            :placeholder="__('Search part number or name')"
                        />
                    </div>
                    <flux:select wire:model.live="perPage" class="w-full sm:w-32">
                        <flux:select.option value="10">10 / page</flux:select.option>
                        <flux:select.option value="25">25 / page</flux:select.option>
                        <flux:select.option value="50">50 / page</flux:select.option>
                    </flux:select>
                </div>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                <thead class="bg-[var(--admin-surface-muted)]">
                    <tr>
                        <x-core::ui.sortable-th method="sortParts" column="part" :label="__('Part')" :sort-by="$partSortBy" :sort-direction="$partSortDirection" />
                        <x-core::ui.sortable-th method="sortParts" column="vendor" :label="__('Vendor')" :sort-by="$partSortBy" :sort-direction="$partSortDirection" />
                        @if ($audience === 'master')
                            <x-core::ui.sortable-th method="sortParts" column="ordered" :label="__('Ordered')" :sort-by="$partSortBy" :sort-direction="$partSortDirection" />
                            <x-core::ui.sortable-th method="sortParts" column="invoiced" :label="__('Invoiced')" :sort-by="$partSortBy" :sort-direction="$partSortDirection" />
                        @endif
                        <x-core::ui.sortable-th method="sortParts" column="received" :label="__('Received')" :sort-by="$partSortBy" :sort-direction="$partSortDirection" />
                        <x-core::ui.sortable-th method="sortParts" column="inspected" :label="__('Inspected')" :sort-by="$partSortBy" :sort-direction="$partSortDirection" />
                        <x-core::ui.sortable-th method="sortParts" column="gap" :label="__('Gap')" :sort-by="$partSortBy" :sort-direction="$partSortDirection" />
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse ($part_rows as $row)
                        <tr wire:key="part-row-{{ $row['id'] }}">
                            <td class="px-4 py-3 text-sm"><span class="font-medium">{{ $row['part_number'] }}</span><br><span class="text-xs text-zinc-500">{{ $row['name'] }}</span></td>
                            <td class="px-4 py-3 text-sm">
                                @forelse ($row['vendors'] as $vendor)
                                    <div @class(['mt-2' => ! $loop->first])>
                                        {{ $vendor['name'] }}
                                        @if ($vendor['code'])
                                            <br><span class="text-xs text-zinc-500">{{ $vendor['code'] }}</span>
                                        @endif
                                    </div>
                                @empty
                                    <span class="text-zinc-400">—</span>
                                @endforelse
                            </td>
                            @if ($audience === 'master')
                                <td class="px-4 py-3 text-sm tabular-nums">{{ Qty::format($row['ordered']) }}</td>
                                <td class="px-4 py-3 text-sm tabular-nums">{{ Qty::format($row['invoiced']) }}</td>
                            @endif
                            <td class="px-4 py-3 text-sm tabular-nums">{{ Qty::format($row['coming']) }}</td>
                            <td class="px-4 py-3 text-sm tabular-nums">{{ Qty::format($row['inspected']) }}</td>
                            <td class="px-4 py-3 text-sm tabular-nums">{{ Qty::format(max(0, $row['coming'] - $row['inspected'])) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ $audience === 'master' ? 7 : 5 }}" class="px-4 py-8 text-center text-sm text-zinc-500">{{ __('No part movement yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-zinc-200 px-4 py-3 dark:border-zinc-700">
            <x-core::ui.pagination :paginator="$part_rows" class="mt-0" />
        </div>
    </section>
    @endif

    @if (in_array($audience, ['master', 'purchase'], true))
    <section class="admin-card">
        <div class="border-b border-zinc-200 px-4 py-3 dark:border-zinc-700">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <flux:heading>{{ __('Purchase order balance') }}</flux:heading>
                <div class="flex w-full flex-col gap-3 sm:w-auto sm:flex-row sm:items-center">
                    <div class="w-full sm:w-80">
                        <flux:input
                            wire:model.live.debounce.300ms="balanceSearch"
                            icon="magnifying-glass"
                            :placeholder="__('Search PO, PI, vendor name, or vendor code')"
                        />
                    </div>
                    <flux:select wire:model.live="perPage" class="w-full sm:w-32">
                        <flux:select.option value="10">10 / page</flux:select.option>
                        <flux:select.option value="25">25 / page</flux:select.option>
                        <flux:select.option value="50">50 / page</flux:select.option>
                    </flux:select>
                </div>
            </div>
        </div>
        <div class="overflow-x-auto">
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
        </div>
        <div class="border-t border-zinc-200 px-4 py-3 dark:border-zinc-700">
            <x-core::ui.pagination :paginator="$balances" class="mt-0" />
        </div>
    </section>
    @endif

    @if (in_array($audience, ['master', 'incoming'], true))
    <section>
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
    </section>
    @endif
</div>
