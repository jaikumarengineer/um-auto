<?php

use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Modules\Quality\Models\IncomingLot;
use Modules\Quality\Models\IncomingPart;
use Modules\Quality\Services\QualityAnalysis;
use Modules\Quality\Support\Qty;

new #[Layout('core::layouts.app')] class extends Component {
    public string $traceMode = 'part';

    public ?int $partId = null;

    public ?int $lotId = null;

    public ?int $compareLotId = null;

    public function mount(): void
    {
        $this->partId = IncomingPart::query()->orderBy('part_number')->value('id');
        $this->lotId = IncomingLot::query()->latest('received_at')->value('id');
        $this->compareLotId = IncomingLot::query()->whereHas('reports')->latest('received_at')->value('id');
    }

    public function audience(): string
    {
        return auth()->user()?->qualityAudience() ?? 'master';
    }

    public function with(QualityAnalysis $analysis): array
    {
        return array_merge(
            $analysis->dashboard($this->partId, $this->lotId, $this->compareLotId, $this->audience()),
            ['audience' => $this->audience()],
        );
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
        <div class="admin-card p-4">
            <flux:heading>{{ __('PO vs PI till now') }}</flux:heading>
            <div class="mt-4">
                <x-core::charts.chart type="bar" :dataset="$po_chart" :height="280" />
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
                    @foreach ([__('PO'), __('Vendor'), __('Status'), __('Ordered'), __('Invoiced'), __('Balance')] as $heading)
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500">{{ $heading }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @foreach ($balances as $row)
                    <tr>
                        <td class="px-4 py-3 text-sm"><a class="text-primary hover:underline" href="{{ $row['url'] }}">{{ $row['po'] }}</a></td>
                        <td class="px-4 py-3 text-sm">{{ $row['vendor'] }}</td>
                        <td class="px-4 py-3"><x-quality::status-pill :status="$row['status']" /> @if ($row['overdue']) <span class="ms-1 text-xs text-red-600">{{ __('Overdue') }}</span> @endif</td>
                        <td class="px-4 py-3 text-sm tabular-nums">{{ Qty::format($row['ordered']) }}</td>
                        <td class="px-4 py-3 text-sm tabular-nums">{{ Qty::format($row['invoiced']) }}</td>
                        <td class="px-4 py-3 text-sm tabular-nums">{{ Qty::format($row['balance']) }}</td>
                    </tr>
                @endforeach
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
