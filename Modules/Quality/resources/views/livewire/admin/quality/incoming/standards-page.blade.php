<?php

use App\Concerns\WithSorting;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Modules\Quality\Models\InspectionStandard;

new #[Layout('core::layouts.app')] class extends Component {
    use WithPagination;
    use WithSorting;

    public string $search = '';

    public string $sortBy = 'code';

    public string $sortDirection = 'asc';

    protected array $sortable = ['code', 'revision', 'effective_from', 'characteristics_count', 'status'];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function with(): array
    {
        return [
            'standards' => $this->applySorting(
                InspectionStandard::query()
                    ->with(['part', 'samplingPlan'])
                    ->withCount('characteristics')
                    ->when($this->search !== '', function ($query) {
                        $term = '%'.$this->search.'%';
                        $query->where(function ($query) use ($term) {
                            $query->where('code', 'like', $term)
                                ->orWhere('title', 'like', $term)
                                ->orWhereHas('part', fn ($part) => $part->where('part_number', 'like', $term)->orWhere('name', 'like', $term));
                        });
                    })
            )->paginate(12),
        ];
    }
}; ?>

<div>
    <x-core::ui.page-header
        :title="__('Incoming inspection standards')"
        :subtitle="__('The characteristics a batch is checked against.')"
        :breadcrumbs="[
            admin_breadcrumb_home(),
            ['label' => __('Inspection standards')],
        ]"
    >
        <x-slot:actions>
            <flux:button variant="primary" icon="plus" :href="route('admin::quality.incoming.standards.create')" wire:navigate>
                {{ __('New standard') }}
            </flux:button>
        </x-slot:actions>
    </x-core::ui.page-header>

    <div class="mb-4 admin-card p-3">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search standard or part')" />
    </div>

    <div class="overflow-x-auto admin-card">
        <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
            <thead class="bg-[var(--admin-surface-muted)]">
                <tr>
                    <x-core::ui.sortable-th column="code" :label="__('Standard')" />
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500">{{ __('Part') }}</th>
                    <x-core::ui.sortable-th column="revision" :label="__('Revision')" />
                    <x-core::ui.sortable-th column="effective_from" :label="__('Effective')" />
                    <x-core::ui.sortable-th column="characteristics_count" :label="__('Checks')" />
                    <x-core::ui.sortable-th column="status" :label="__('Status')" />
                    <th></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse ($standards as $standard)
                    <tr wire:key="standard-{{ $standard->id }}">
                        <td class="px-4 py-3 text-sm font-medium">{{ $standard->code }}<br><span class="text-xs font-normal text-zinc-500">{{ $standard->title }}</span></td>
                        <td class="px-4 py-3 text-sm">{{ $standard->part?->part_number }}</td>
                        <td class="px-4 py-3 text-sm">{{ $standard->revision }}</td>
                        <td class="px-4 py-3 text-sm">{{ $standard->effective_from?->format('d M Y') }}</td>
                        <td class="px-4 py-3 text-sm">{{ $standard->characteristics_count }}</td>
                        <td class="px-4 py-3"><x-quality::status-pill :status="$standard->status" /></td>
                        <td class="px-4 py-3 text-right">
                            <flux:button size="sm" variant="ghost" :href="route('admin::quality.incoming.standards.show', $standard)" wire:navigate>{{ __('Open') }}</flux:button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-12 text-center text-sm text-zinc-500">{{ __('No inspection standards yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <x-core::ui.pagination :paginator="$standards" />
</div>
