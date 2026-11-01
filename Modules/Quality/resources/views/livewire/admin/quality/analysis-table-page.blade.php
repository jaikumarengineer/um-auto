<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Modules\Quality\Services\QualityAnalysis;
use Modules\Quality\Support\QualityAlertCatalog;
use Modules\Quality\Support\Qty;

new #[Layout('core::layouts.app')] class extends Component {
    #[Url]
    public string $department = 'incoming';

    /** @var 'current'|'month'|'year'|'mtd' */
    #[Url]
    public string $period = 'current';

    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    public string $graphDateRange = '';

    public function mount(): void
    {
        $audience = $this->audience();

        if ($audience !== 'master') {
            $this->department = $audience;
        } elseif (! in_array($this->department, ['incoming', 'purchase'], true)) {
            $this->department = 'incoming';
        }

        if (! in_array($this->period, ['current', 'month', 'year', 'mtd'], true)) {
            $this->period = 'current';
        }

        if ($this->from === '' || $this->to === '' || ! strtotime($this->from) || ! strtotime($this->to)) {
            $this->applyPeriodDefaults($this->period);

            return;
        }

        $this->graphDateRange = $this->from.' to '.$this->to;
    }

    public function audience(): string
    {
        return auth()->user()?->qualityAudience() ?? 'master';
    }

    public function updatedDepartment(string $department): void
    {
        if ($this->audience() !== 'master') {
            $this->department = $this->audience();

            return;
        }

        if (! in_array($department, ['incoming', 'purchase'], true)) {
            $this->department = 'incoming';
        }
    }

    public function updatedPeriod(string $period): void
    {
        if (! in_array($period, ['current', 'month', 'year', 'mtd'], true)) {
            $this->period = 'current';
            $period = 'current';
        }

        $this->applyPeriodDefaults($period);
    }

    public function updatedGraphDateRange(string $range): void
    {
        [$from, $to] = $this->parseRange($range);
        $this->from = $from;
        $this->to = $to;
    }

    protected function applyPeriodDefaults(string $period): void
    {
        [$from, $to] = match ($period) {
            'month' => [now()->subMonths(11)->startOfMonth(), now()],
            'year' => [now()->subYears(2)->startOfYear(), now()],
            'mtd' => [now()->subYear()->startOfYear(), now()],
            default => [now()->startOfMonth(), now()],
        };

        $this->from = $from->toDateString();
        $this->to = $to->toDateString();
        $this->graphDateRange = $this->from.' to '.$this->to;
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function parseRange(string $range): array
    {
        $parts = preg_split('/\s+to\s+/i', trim($range)) ?: [];
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

    public function pageTitle(): string
    {
        return match ($this->resolvedDepartment()) {
            'purchase' => __('Purchase Department'),
            'incoming' => __('Incoming Department'),
            default => __('Quality analysis'),
        };
    }

    public function resolvedDepartment(): string
    {
        $audience = $this->audience();

        return $audience === 'master' ? $this->department : $audience;
    }

    public function with(QualityAnalysis $analysis): array
    {
        $department = $this->resolvedDepartment();
        [$from, $to] = $this->parseRange(
            $this->graphDateRange !== '' ? $this->graphDateRange : ($this->from.' to '.$this->to)
        );

        $table = in_array($department, ['incoming', 'purchase'], true)
            ? $analysis->departmentGraphRows($department, $this->period, $from, $to)
            : null;

        $periodLabels = [
            'current' => __('Current month'),
            'month' => __('Month wise'),
            'year' => __('Yearly'),
            'mtd' => __('MTD / YTD'),
        ];

        return [
            'audience' => $this->audience(),
            'departments' => collect(QualityAlertCatalog::departmentLabels())
                ->only(['incoming', 'purchase'])
                ->all(),
            'table' => $table,
            'page_title' => $this->pageTitle(),
            'period_label' => $periodLabels[$this->period] ?? $this->period,
            'range_from' => $from,
            'range_to' => $to,
        ];
    }
}; ?>

<div class="space-y-6">
    <x-core::ui.page-header
        :title="$table['title'] ?? $page_title"
        :subtitle="__('Chart data for verification. Filters match the analysis graph.')"
        :breadcrumbs="[
            admin_breadcrumb_home(),
            ['label' => __('Analysis'), 'route' => 'admin::quality.analysis'],
            ['label' => __('Chart table')],
        ]"
    >
        <x-slot:actions>
            <div class="w-48">
                <flux:select wire:model.live="period" :label="__('Period')">
                    <flux:select.option value="current">{{ __('Current month') }}</flux:select.option>
                    <flux:select.option value="month">{{ __('Month wise') }}</flux:select.option>
                    <flux:select.option value="year">{{ __('Yearly') }}</flux:select.option>
                    <flux:select.option value="mtd">{{ __('MTD / YTD') }}</flux:select.option>
                </flux:select>
            </div>
            <div class="w-64" wire:key="table-range-picker-{{ $period }}">
                <x-core::forms.flatpickr
                    wire:model.live="graphDateRange"
                    mode="range"
                    :label="__('Date range')"
                    :placeholder="__('Select date range…')"
                />
            </div>
            @if ($audience === 'master')
                <div class="w-44">
                    <flux:select wire:model.live="department" :label="__('Department')">
                        @foreach ($departments as $key => $label)
                            <flux:select.option :value="$key">{{ $label }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
            @endif
        </x-slot:actions>
    </x-core::ui.page-header>

    @if ($table)
        <section class="admin-card">
            <div class="border-b border-zinc-200 px-4 py-3 dark:border-zinc-700">
                <flux:heading>{{ $table['title'] }}</flux:heading>
                @if ($table['subtitle'] !== '')
                    <p class="mt-1 text-sm text-zinc-500">{{ $table['subtitle'] }}</p>
                @endif
                <p class="mt-2 text-xs text-zinc-500">
                    {{ __('Period: :period · :from to :to', ['period' => $period_label, 'from' => $range_from, 'to' => $range_to]) }}
                </p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                    <thead class="bg-[var(--admin-surface-muted)]">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ __('Label') }}</th>
                            @foreach ($table['columns'] as $column)
                                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ $column }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        @forelse ($table['rows'] as $index => $row)
                            <tr wire:key="graph-row-{{ $index }}">
                                <td class="px-4 py-3 text-sm font-medium">{{ $row['label'] }}</td>
                                @foreach ($row['values'] as $value)
                                    <td class="px-4 py-3 text-right text-sm tabular-nums">
                                        {{ $value === null ? '—' : Qty::format($value) }}
                                    </td>
                                @endforeach
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ count($table['columns']) + 1 }}" class="px-4 py-8 text-center text-sm text-zinc-500">
                                    {{ __('No chart data for this range.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if ($table['rows'] !== [])
                        <tfoot class="border-t border-zinc-200 bg-[var(--admin-surface-muted)] dark:border-zinc-700">
                            <tr>
                                <td class="px-4 py-3 text-sm font-semibold">{{ __('Total') }}</td>
                                @foreach ($table['totals'] as $total)
                                    <td class="px-4 py-3 text-right text-sm font-semibold tabular-nums">
                                        {{ $total === null ? '—' : Qty::format($total) }}
                                    </td>
                                @endforeach
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </section>
    @else
        <section class="admin-card p-4">
            <flux:heading>{{ $page_title }}</flux:heading>
            <p class="mt-2 text-sm text-zinc-500">{{ __('No graph is set up for this department yet.') }}</p>
        </section>
    @endif
</div>
