<?php

use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Modules\Quality\Models\InspectionReport;
use Modules\Quality\Support\Qty;

new #[Layout('core::layouts.app')] class extends Component {
    public InspectionReport $report;

    public function mount(InspectionReport $report): void
    {
        $this->report = $report->load(['lot.part', 'lot.vendor', 'standard.characteristics', 'samplingPlan', 'results']);
    }
}; ?>

<div>
    <x-core::ui.page-header
        :title="$report->report_number"
        :subtitle="($report->lot?->lot_number).' · '.($report->lot?->part?->label())"
        :breadcrumbs="[
            admin_breadcrumb_home(),
            ['label' => __('Inspection reports'), 'route' => 'admin::quality.incoming.reports'],
            ['label' => $report->report_number],
        ]"
    >
        <x-slot:actions>
            <x-quality::status-pill :status="$report->result" />
        </x-slot:actions>
    </x-core::ui.page-header>

    <div class="mb-4 grid gap-4 md:grid-cols-4">
        @foreach ([
            __('Batch qty') => Qty::format($report->inspected_qty),
            __('Accepted') => Qty::format($report->accepted_qty),
            __('Rejected') => Qty::format($report->rejected_qty),
            __('Sample') => (string) $report->sample_size,
        ] as $label => $value)
            <div class="admin-card p-4">
                <p class="text-sm text-zinc-500">{{ $label }}</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums">{{ $value }}</p>
            </div>
        @endforeach
    </div>

    <div class="mb-4 admin-card p-4 text-sm text-zinc-600 dark:text-zinc-300">
        <p>{{ __('Standard') }}: {{ $report->standard?->code }} · {{ $report->standard?->title }}</p>
        <p>{{ __('Sampling plan') }}: {{ $report->samplingPlan?->code ?: '—' }} {{ $report->samplingPlan ? '· '.$report->samplingPlan->standard_ref.' AQL '.$report->samplingPlan->aql : '' }}</p>
        <p>{{ __('Inspected') }} {{ $report->inspected_at?->format('d M Y H:i') }} · {{ $report->lot?->vendor?->name }}</p>
        @if ($report->remarks)
            <p class="mt-2">{{ $report->remarks }}</p>
        @endif
    </div>

    <div class="overflow-x-auto admin-card">
        <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
            <thead class="bg-[var(--admin-surface-muted)]">
                <tr>
                    @foreach ([__('Characteristic'), __('Standard'), __('Measured'), __('Result')] as $heading)
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500">{{ $heading }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @foreach ($report->results as $result)
                    <tr>
                        <td class="px-4 py-3 text-sm">
                            {{ $result->characteristic_name }}
                            @if ($result->is_critical)
                                <span class="ms-1 text-xs text-red-600">{{ __('Critical') }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-sm text-zinc-500">{{ $result->specification }}</td>
                        <td class="px-4 py-3 text-sm">{{ $result->measured_value ?: '—' }}</td>
                        <td class="px-4 py-3"><x-quality::status-pill :status="$result->result" /></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
