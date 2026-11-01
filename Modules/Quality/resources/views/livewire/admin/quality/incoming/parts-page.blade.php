<?php

use App\Concerns\AuthorizesModule;
use App\Concerns\NotifiesWithSweetAlert;
use App\Concerns\WithSorting;
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
    use AuthorizesModule;
    use NotifiesWithSweetAlert;
    use WithPagination;
    use WithSorting;

    private const MODULE = 'quality.incoming.parts';

    public string $search = '';

    public string $sortBy = 'part_number';

    public string $sortDirection = 'asc';

    protected array $sortable = ['part_number', 'name', 'uom', 'approved_on'];

    public bool $showModal = false;

    public ?int $partId = null;

    public string $part_number = '';

    public string $name = '';

    public string $description = '';

    public string $uom = 'PCS';

    public string $category_id = '';

    /** @var list<string> */
    public array $vendor_ids = [];

    public string $approved_on = '';

    public bool $is_active = true;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function createPart(): void
    {
        $this->authorizeModule(self::MODULE, 'add');

        $this->reset(['partId', 'part_number', 'name', 'description', 'category_id', 'approved_on']);
        $this->vendor_ids = [];
        $this->uom = 'PCS';
        $this->approved_on = now()->toDateString();
        $this->is_active = true;
        $this->resetValidation();
        $this->showModal = true;
    }

    public function editPart(int $id): void
    {
        $this->authorizeModule(self::MODULE, 'edit');

        $part = IncomingPart::query()->with('vendors')->findOrFail($id);
        $this->partId = $part->id;
        $this->part_number = $part->part_number;
        $this->name = $part->name;
        $this->description = (string) $part->description;
        $this->uom = $part->uom;
        $this->category_id = (string) ($part->category_id ?? '');
        $this->vendor_ids = $part->vendors->pluck('id')->map(fn ($id) => (string) $id)->values()->all();

        if ($this->vendor_ids === [] && $part->vendor_id) {
            $this->vendor_ids = [(string) $part->vendor_id];
        }

        $this->approved_on = $part->approved_on?->toDateString() ?? '';
        $this->is_active = $part->is_active;
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetValidation();
    }

    public function savePart(): void
    {
        $data = $this->validate([
            'part_number' => ['required', 'string', 'max:60', Rule::unique('incoming_parts', 'part_number')->ignore($this->partId)],
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string'],
            'uom' => ['required', 'string', 'max:20'],
            'category_id' => ['nullable', 'exists:part_categories,id'],
            'vendor_ids' => ['nullable', 'array'],
            'vendor_ids.*' => ['integer', 'exists:approved_vendors,id'],
            'approved_on' => ['nullable', 'date'],
            'is_active' => ['boolean'],
        ]);

        $data['category_id'] = $data['category_id'] ?: null;
        $data['approved_on'] = $data['approved_on'] ?: null;
        unset($data['vendor_ids']);

        $part = $this->partId ? IncomingPart::query()->findOrFail($this->partId) : new IncomingPart();
        $part->fill($data)->save();
        $part->syncVendors($this->vendor_ids);
        $this->showModal = false;
        $this->notifySuccess(__('Part saved.'));
    }

    public function deletePart(int $id): void
    {
        $this->authorizeModule(self::MODULE, 'delete');

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
            'parts' => $this->applySorting(
                IncomingPart::query()
                    ->with(['vendors', 'vendor', 'category'])
                    ->withExists('standards')
                    ->when($this->search !== '', function ($query) {
                        $term = '%'.$this->search.'%';
                        $query->where(function ($query) use ($term) {
                            $query->where('part_number', 'like', $term)
                                ->orWhere('name', 'like', $term)
                                ->orWhereHas('category', fn ($category) => $category->where('name', 'like', $term))
                                ->orWhereHas('vendors', function ($vendors) use ($term) {
                                    $vendors->where('name', 'like', $term)->orWhere('code', 'like', $term);
                                });
                        });
                    })
            )->paginate(12),
            'vendors' => ApprovedVendor::query()->orderBy('name')->get(),
            'categories' => PartCategory::query()->orderBy('name')->get(),
            'canView' => module_can(self::MODULE, 'view'),
            'canAdd' => module_can(self::MODULE, 'add'),
            'canEdit' => module_can(self::MODULE, 'edit'),
            'canDelete' => module_can(self::MODULE, 'delete'),
            'canViewStandard' => module_can('quality.incoming.standards', 'view'),
            'canEditStandard' => module_can('quality.incoming.standards', 'edit'),
        ];
    }
}; ?>

<div>
    <x-core::ui.page-header
        :title="__('Incoming Quality parts')"
        :subtitle="__('Showing the list of incoming quality parts')"
        :breadcrumbs="[
            admin_breadcrumb_home(),
            ['label' => __('Incoming Quality parts')],
        ]"
    >
        <x-slot:actions>
            @if ($canAdd)
                <flux:button variant="primary" icon="plus" wire:click="createPart">{{ __('Add part') }}</flux:button>
            @endif
        </x-slot:actions>
    </x-core::ui.page-header>

    <div class="mb-4 admin-card p-3">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search part code or description')" />
    </div>

    <div class="overflow-x-auto admin-card">
        <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
            <thead class="bg-[var(--admin-surface-muted)]">
                <tr>
                    <x-core::ui.sortable-th column="part_number" :label="__('Material / Part Code')" />
                    <x-core::ui.sortable-th column="name" :label="__('Material / Part Description')" />
                    <th class="px-4 py-3 text-left text-xs font-semibold text-zinc-500">{{ __('Vendor Details') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-zinc-500">{{ __('Description') }}</th>
                    <x-core::ui.sortable-th column="uom" :label="__('UOM')" />
                    <x-core::ui.sortable-th column="approved_on" :label="__('Material/Part approved Date')" />
                    <th class="px-4 py-3 text-left text-xs font-semibold text-zinc-500">{{ __('Incoming standard') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse ($parts as $part)
                    <tr wire:key="part-{{ $part->id }}">
                        <td class="px-4 py-3 text-sm font-medium">{{ $part->part_number }}</td>
                        <td class="px-4 py-3 text-sm">{{ $part->name }}<br><span class="text-xs text-zinc-500">{{ $part->category?->name }}</span></td>
                        <td class="px-4 py-3 text-sm">{{ $part->vendorsLabel() }}</td>
                        <x-core::ui.long-text-cell :value="$part->description" />
                        <td class="px-4 py-3 text-sm">{{ $part->uom }}</td>
                        <td class="px-4 py-3 text-sm">{{ $part->approved_on?->format('d M Y') ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm">
                            @if (($part->standards_exists && ($canViewStandard || $canEditStandard)) || (! $part->standards_exists && $canEditStandard))
                                <a
                                    href="{{ route('admin::quality.incoming.parts.standard', $part) }}"
                                    wire:navigate
                                    class="inline-flex rounded-lg p-1.5 text-zinc-600 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800"
                                    title="{{ $part->standards_exists ? __('Open incoming standard') : __('Add incoming standard') }}"
                                >
                                    @if ($part->standards_exists)
                                        <flux:icon.document-text class="size-5" />
                                    @else
                                        <flux:icon.plus class="size-5" />
                                    @endif
                                </a>
                            @else
                                <span class="text-zinc-400">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            @if ($canEdit || $canDelete)
                                <x-core::ui.actions-menu>
                                    @if ($canEdit)
                                        <flux:menu.item icon="pencil-square" icon:variant="outline" wire:click="editPart({{ $part->id }})">{{ __('Edit') }}</flux:menu.item>
                                    @endif
                                    @if ($canDelete)
                                        @if ($canEdit)
                                            <flux:menu.separator />
                                        @endif
                                        <x-core::ui.confirm-button as="menu-item" method="deletePart" :params="[$part->id]" />
                                    @endif
                                </x-core::ui.actions-menu>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-4 py-12 text-center text-sm text-zinc-500">{{ __('No parts yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <x-core::ui.pagination :paginator="$parts" />

    <flux:modal wire:model="showModal" class="w-full !max-w-2xl max-h-[90vh] overflow-y-auto">
        <form wire:submit.prevent="savePart" class="space-y-4">
            <div>
                <flux:heading size="lg">{{ $partId ? __('Edit part') : __('Add part') }}</flux:heading>
            </div>
            <div class="space-y-3">
                <flux:input wire:model="part_number" :label="__('Material / Part Code')" required />
                <flux:input wire:model="name" :label="__('Material / Part Description')" required />
                <div class="grid grid-cols-2 gap-3">
                    <flux:input wire:model="uom" :label="__('UOM')" />
                    @if ($showModal)
                        <x-core::forms.select2
                            wire:key="part-category-{{ $partId ?? 'new' }}"
                            wire:model="category_id"
                            :label="__('Category')"
                            :options="$categories->mapWithKeys(fn ($category) => [$category->id => $category->name])"
                            :placeholder="__('Select category')"
                        />
                    @endif
                </div>
                @if ($showModal)
                    <x-core::forms.select2
                        wire:key="part-vendors-{{ $partId ?? 'new' }}"
                        wire:model="vendor_ids"
                        :label="__('Vendor Details')"
                        :options="$vendors->mapWithKeys(fn ($vendor) => [$vendor->id => $vendor->optionLabel()])"
                        :placeholder="__('Select one or more vendors')"
                        :multiple="true"
                        :allow-clear="true"
                    />
                @endif
                <flux:input wire:model="approved_on" type="date" :label="__('Material/Part approved Date')" />
                <flux:textarea wire:model="description" :label="__('Description')" rows="2" />
                <flux:switch wire:model="is_active" :label="__('Active')" />
            </div>
            <div class="flex justify-end gap-2 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                <flux:button type="button" variant="ghost" wire:click="closeModal">{{ __('Cancel') }}</flux:button>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="savePart">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
