<?php

use App\Concerns\WithSorting;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Modules\Quality\Models\InspectionReport;
use Modules\Quality\Support\Qty;

new #[Layout('core::layouts.app')] class extends Component {
    use WithPagination;
    use WithSorting;

    public string $search = '';

    public string $resultFilter = '';

    public string $sortBy = 'inspected_at';

    public string $sortDirection = 'desc';

    protected array $sortable = ['report_number', 'inspected_qty', 'sample_size', 'inspected_at', 'result'];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingResultFilter(): void
    {
        $this->resetPage();
    }

    public function with(): array
    {
        return [
            'reports' => $this->applySorting(
                InspectionReport::query()
                    ->with(['lot.part', 'standard'])
                    ->when($this->search !== '', function ($query) {
                        $term = '%'.$this->search.'%';
                        $query->where(function ($query) use ($term) {
                            $query->where('report_number', 'like', $term)
                                ->orWhereHas('lot', fn ($lot) => $lot->where('lot_number', 'like', $term))
                                ->orWhereHas('lot.part', fn ($part) => $part->where('part_number', 'like', $term));
                        });
                    })
                    ->when($this->resultFilter !== '', fn ($query) => $query->where('result', $this->resultFilter))
            )->paginate(12),
        ];
    }
}; ?>

<div>
    <x-core::ui.page-header
        :title="__('Inspection reports')"
        :subtitle="__('Each report is kept, so a batch can be inspected again without losing the earlier result.')"
        :breadcrumbs="[
            admin_breadcrumb_home(),
            ['label' => __('Inspection reports')],
        ]"
    >
        <x-slot:actions>
            <flux:button variant="primary" icon="plus" :href="route('admin::quality.incoming.reports.create')" wire:navigate>
                {{ __('New report') }}
            </flux:button>
        </x-slot:actions>
    </x-core::ui.page-header>

    <div class="mb-4 admin-card p-3">
        <div class="flex flex-col gap-3 lg:flex-row">
            <div class="min-w-0 flex-1">
                <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search report, lot, or part')" />
            </div>
            <flux:select wire:model.live="resultFilter" class="lg:w-44">
                <flux:select.option value="">{{ __('All results') }}</flux:select.option>
                @foreach (['pass', 'conditional', 'fail'] as $result)
                    <flux:select.option :value="$result">{{ str($result)->title() }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
    </div>

    <div class="overflow-x-auto admin-card">
        <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
            <thead class="bg-[var(--admin-surface-muted)]">
                <tr>
                    <x-core::ui.sortable-th column="report_number" :label="__('Report')" />
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500">{{ __('Batch') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500">{{ __('Part') }}</th>
                    <x-core::ui.sortable-th column="inspected_qty" :label="__('Qty')" />
                    <x-core::ui.sortable-th column="sample_size" :label="__('Sample')" />
                    <x-core::ui.sortable-th column="inspected_at" :label="__('When')" />
                    <x-core::ui.sortable-th column="result" :label="__('Result')" />
                    <th></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse ($reports as $report)
                    <tr wire:key="report-{{ $report->id }}">
                        <td class="px-4 py-3 text-sm font-medium">{{ $report->report_number }}</td>
                        <td class="px-4 py-3 text-sm">{{ $report->lot?->lot_number }}</td>
                        <td class="px-4 py-3 text-sm">{{ $report->lot?->part?->part_number }}</td>
                        <td class="px-4 py-3 text-sm tabular-nums">{{ Qty::format($report->inspected_qty) }}</td>
                        <td class="px-4 py-3 text-sm">{{ $report->sample_size }}</td>
                        <td class="px-4 py-3 text-sm">{{ $report->inspected_at?->format('d M Y H:i') }}</td>
                        <td class="px-4 py-3"><x-quality::status-pill :status="$report->result" /></td>
                        <td class="px-4 py-3 text-right">
                            <flux:button size="sm" variant="ghost" :href="route('admin::quality.incoming.reports.show', $report)" wire:navigate>{{ __('Open') }}</flux:button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-4 py-12 text-center text-sm text-zinc-500">{{ __('No inspection reports yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <x-core::ui.pagination :paginator="$reports" />
</div>
