<?php

use App\Concerns\NotifiesWithSweetAlert;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Modules\Quality\Models\ApprovedVendor;
use Modules\Quality\Models\IncomingLot;
use Modules\Quality\Models\IncomingPart;
use Modules\Quality\Models\PartCategory;
use Modules\Quality\Models\PurchaseOrderLine;

new #[Layout('core::layouts.app')] class extends Component {
    use NotifiesWithSweetAlert;
    use WithPagination;

    public string $search = '';

    public bool $showFlyout = false;

    public ?int $partId = null;

    public string $part_number = '';

    public string $name = '';

    public string $description = '';

    public string $uom = 'PCS';

    public string $category_id = '';

    public string $vendor_id = '';

    public string $drawing_revision = '';

    public bool $is_active = true;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function createPart(): void
    {
        $this->reset(['partId', 'part_number', 'name', 'description', 'category_id', 'vendor_id', 'drawing_revision']);
        $this->uom = 'PCS';
        $this->is_active = true;
        $this->resetValidation();
        $this->showFlyout = true;
    }

    public function editPart(int $id): void
    {
        $part = IncomingPart::query()->findOrFail($id);
        $this->partId = $part->id;
        $this->part_number = $part->part_number;
        $this->name = $part->name;
        $this->description = (string) $part->description;
        $this->uom = $part->uom;
        $this->category_id = (string) ($part->category_id ?? '');
        $this->vendor_id = (string) ($part->vendor_id ?? '');
        $this->drawing_revision = (string) $part->drawing_revision;
        $this->is_active = $part->is_active;
        $this->showFlyout = true;
    }

    public function savePart(): void
    {
        $data = $this->validate([
            'part_number' => ['required', 'string', 'max:60', Rule::unique('incoming_parts', 'part_number')->ignore($this->partId)],
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string'],
            'uom' => ['required', 'string', 'max:20'],
            'category_id' => ['nullable', 'exists:part_categories,id'],
            'vendor_id' => ['nullable', 'exists:approved_vendors,id'],
            'drawing_revision' => ['nullable', 'string', 'max:20'],
            'is_active' => ['boolean'],
        ]);

        $data['category_id'] = $data['category_id'] ?: null;
        $data['vendor_id'] = $data['vendor_id'] ?: null;
        $part = $this->partId ? IncomingPart::query()->findOrFail($this->partId) : new IncomingPart();
        $part->fill($data)->save();
        $this->showFlyout = false;
        $this->notifySuccess(__('Part saved.'));
    }

    public function deletePart(int $id): void
    {
        $part = IncomingPart::query()->findOrFail($id);

        if ($part->standards()->exists() || PurchaseOrderLine::query()->where('part_id', $part->id)->exists() || IncomingLot::query()->where('part_id', $part->id)->exists()) {
            $this->notifyError(__('This part is already in use and cannot be deleted.'));

            return;
        }

        $part->delete();
        $this->notifySuccess(__('Part deleted.'));
    }

    public function with(): array
    {
        return [
            'parts' => IncomingPart::query()
                ->with(['vendor', 'category'])
                ->withCount('standards')
                ->when($this->search !== '', function ($query) {
                    $term = '%'.$this->search.'%';
                    $query->where(function ($query) use ($term) {
                        $query->where('part_number', 'like', $term)
                            ->orWhere('name', 'like', $term)
                            ->orWhereHas('category', fn ($category) => $category->where('name', 'like', $term));
                    });
                })
                ->orderBy('part_number')
                ->paginate(12),
            'vendors' => ApprovedVendor::query()->orderBy('name')->get(),
            'categories' => PartCategory::query()->orderBy('name')->get(),
        ];
    }
}; ?>

<div>
    <x-core::ui.page-header
        :title="__('Incoming parts')"
        :subtitle="__('Parts that can be ordered, received in batches, and inspected.')"
        :breadcrumbs="[
            admin_breadcrumb_home(),
            ['label' => __('Parts')],
        ]"
    >
        <x-slot:actions>
            <flux:button variant="primary" icon="plus" wire:click="createPart">{{ __('Add part') }}</flux:button>
        </x-slot:actions>
    </x-core::ui.page-header>

    <div class="mb-4 admin-card p-3">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search part number or name')" />
    </div>

    <div class="overflow-x-auto admin-card">
        <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
            <thead class="bg-[var(--admin-surface-muted)]">
                <tr>
                    @foreach ([__('Part'), __('Name'), __('Vendor'), __('UOM'), __('Rev'), __('Standards')] as $heading)
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500">{{ $heading }}</th>
                    @endforeach
                    <th></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse ($parts as $part)
                    <tr wire:key="part-{{ $part->id }}">
                        <td class="px-4 py-3 text-sm font-medium">{{ $part->part_number }}</td>
                        <td class="px-4 py-3 text-sm">{{ $part->name }}<br><span class="text-xs text-zinc-500">{{ $part->category?->name }}</span></td>
                        <td class="px-4 py-3 text-sm">{{ $part->vendor?->name ?: '—' }}</td>
                        <td class="px-4 py-3 text-sm">{{ $part->uom }}</td>
                        <td class="px-4 py-3 text-sm">{{ $part->drawing_revision ?: '—' }}</td>
                        <td class="px-4 py-3 text-sm">{{ $part->standards_count }}</td>
                        <td class="px-4 py-3 text-right">
                            <x-core::ui.actions-menu>
                                <flux:menu.item icon="pencil-square" icon:variant="outline" wire:click="editPart({{ $part->id }})">{{ __('Edit') }}</flux:menu.item>
                                <flux:menu.separator />
                                <x-core::ui.confirm-button as="menu-item" method="deletePart" :params="[$part->id]" />
                            </x-core::ui.actions-menu>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-12 text-center text-sm text-zinc-500">{{ __('No parts yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($parts->hasPages()) <div class="mt-4">{{ $parts->links() }}</div> @endif

    <flux:modal wire:model="showFlyout" variant="flyout" class="md:min-w-[28rem] !rounded-s-3xl !ps-8 !pe-8">
        <form wire:submit="savePart" class="flex h-full flex-col gap-4">
            <flux:heading size="lg">{{ $partId ? __('Edit part') : __('Add part') }}</flux:heading>
            <div class="flex-1 space-y-3 pe-2">
                <flux:input wire:model="part_number" :label="__('Part number')" required />
                <flux:input wire:model="name" :label="__('Name')" required />
                <div class="grid grid-cols-2 gap-3">
                    <flux:input wire:model="uom" :label="__('UOM')" />
                    @if ($showFlyout)
                        <x-core::forms.select2
                            wire:key="part-category-{{ $partId ?? 'new' }}"
                            wire:model="category_id"
                            :label="__('Category')"
                            :options="$categories->mapWithKeys(fn ($category) => [$category->id => $category->name])"
                            :placeholder="__('Select category')"
                        />
                    @endif
                </div>
                @if ($showFlyout)
                    <x-core::forms.select2
                        wire:key="part-vendor-{{ $partId ?? 'new' }}"
                        wire:model="vendor_id"
                        :label="__('Preferred vendor')"
                        :options="$vendors->mapWithKeys(fn ($vendor) => [$vendor->id => $vendor->name])"
                        :placeholder="__('None')"
                    />
                @endif
                <flux:input wire:model="drawing_revision" :label="__('Drawing revision')" />
                <flux:textarea wire:model="description" :label="__('Description')" rows="2" />
                <flux:switch wire:model="is_active" :label="__('Active')" />
            </div>
            <div class="flex justify-end gap-2 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                <flux:button type="button" variant="ghost" wire:click="$set('showFlyout', false)">{{ __('Cancel') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
