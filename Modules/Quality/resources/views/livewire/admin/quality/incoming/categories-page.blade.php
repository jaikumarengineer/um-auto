<?php

use App\Concerns\AuthorizesModule;
use App\Concerns\NotifiesWithSweetAlert;
use App\Concerns\WithSorting;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Modules\Quality\Models\PartCategory;

new #[Layout('core::layouts.app')] class extends Component {
    use AuthorizesModule;
    use NotifiesWithSweetAlert;
    use WithPagination;
    use WithSorting;

    private const MODULE = 'quality.incoming.categories';

    public string $search = '';

    public string $sortBy = 'name';

    public string $sortDirection = 'asc';

    protected array $sortable = ['name', 'parts_count'];

    public bool $showModal = false;

    public ?int $categoryId = null;

    public string $name = '';

    public string $notes = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function createCategory(): void
    {
        $this->authorizeModule(self::MODULE, 'add');

        $this->reset(['categoryId', 'name', 'notes']);
        $this->resetValidation();
        $this->showModal = true;
    }

    public function editCategory(int $id): void
    {
        $this->authorizeModule(self::MODULE, 'edit');

        $category = PartCategory::query()->findOrFail($id);
        $this->categoryId = $category->id;
        $this->name = $category->name;
        $this->notes = (string) $category->notes;
        $this->resetValidation();
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetValidation();
    }

    public function saveCategory(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:80', Rule::unique('part_categories', 'name')->ignore($this->categoryId)],
            'notes' => ['nullable', 'string'],
        ]);

        $data['notes'] = $data['notes'] ?: null;
        $category = $this->categoryId ? PartCategory::query()->findOrFail($this->categoryId) : new PartCategory;
        $category->fill($data)->save();
        $this->showModal = false;
        $this->notifySuccess(__('Category saved.'));
    }

    public function deleteCategory(int $id): void
    {
        $this->authorizeModule(self::MODULE, 'delete');

        $category = PartCategory::query()->withCount('parts')->findOrFail($id);

        if ($category->parts_count > 0) {
            $this->notifyError(__('This category is used on a part and cannot be deleted.'));

            return;
        }

        $category->delete();
        $this->notifySuccess(__('Category deleted.'));
    }

    public function with(): array
    {
        return [
            'categories' => $this->applySorting(
                PartCategory::query()
                    ->withCount('parts')
                    ->when($this->search !== '', fn ($query) => $query->where('name', 'like', '%'.$this->search.'%'))
            )->paginate(12),
            'canView' => module_can(self::MODULE, 'view'),
            'canAdd' => module_can(self::MODULE, 'add'),
            'canEdit' => module_can(self::MODULE, 'edit'),
            'canDelete' => module_can(self::MODULE, 'delete'),
        ];
    }
}; ?>

<div>
    <x-core::ui.page-header
        :title="__('List of Category')"
        :subtitle="__('Showing the list of categories')"
        :breadcrumbs="[
            admin_breadcrumb_home(),
            ['label' => __('List of Category')],
        ]"
    >
        <x-slot:actions>
            @if ($canAdd)
                <flux:button variant="primary" icon="plus" wire:click="createCategory">{{ __('Add category') }}</flux:button>
            @endif
        </x-slot:actions>
    </x-core::ui.page-header>

    <div class="mb-4 admin-card p-3">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search category')" />
    </div>

    <div class="overflow-x-auto admin-card">
        <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
            <thead class="bg-[var(--admin-surface-muted)]">
                <tr>
                    <x-core::ui.sortable-th column="name" :label="__('Category Name')" />
                    <x-core::ui.sortable-th column="parts_count" :label="__('Part’s Count / No. of Parts')" />
                    <th class="px-4 py-3 text-left text-xs font-semibold text-zinc-500">{{ __('Note') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse ($categories as $category)
                    <tr wire:key="category-{{ $category->id }}">
                        <td class="px-4 py-3 text-sm font-medium">{{ $category->name }}</td>
                        <td class="px-4 py-3 text-sm">{{ $category->parts_count }}</td>
                        <x-core::ui.long-text-cell :value="$category->notes" />
                        <td class="px-4 py-3 text-right">
                            @if ($canEdit || $canDelete)
                                <x-core::ui.actions-menu>
                                    @if ($canEdit)
                                        <flux:menu.item icon="pencil-square" icon:variant="outline" wire:click="editCategory({{ $category->id }})">{{ __('Edit') }}</flux:menu.item>
                                    @endif
                                    @if ($canDelete)
                                        @if ($canEdit)
                                            <flux:menu.separator />
                                        @endif
                                        <x-core::ui.confirm-button as="menu-item" method="deleteCategory" :params="[$category->id]" />
                                    @endif
                                </x-core::ui.actions-menu>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-12 text-center text-sm text-zinc-500">{{ __('No categories yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <x-core::ui.pagination :paginator="$categories" />

    <flux:modal wire:model="showModal" class="w-full !max-w-2xl max-h-[90vh] overflow-y-auto">
        <form wire:submit.prevent="saveCategory" class="space-y-4">
            <div>
                <flux:heading size="lg">{{ $categoryId ? __('Edit category') : __('Add category') }}</flux:heading>
            </div>
            <div class="space-y-3">
                <flux:input wire:model="name" :label="__('Name')" required />
                <flux:textarea wire:model="notes" :label="__('Notes')" rows="2" />
            </div>
            <div class="flex justify-end gap-2 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                <flux:button type="button" variant="ghost" wire:click="closeModal">{{ __('Cancel') }}</flux:button>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="saveCategory">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
