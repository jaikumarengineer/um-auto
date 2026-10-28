<?php

use App\Concerns\NotifiesWithSweetAlert;
use App\Concerns\WithSorting;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Modules\Quality\Models\PartCategory;

new #[Layout('core::layouts.app')] class extends Component {
    use NotifiesWithSweetAlert;
    use WithPagination;
    use WithSorting;

    public string $search = '';

    public string $sortBy = 'name';

    public string $sortDirection = 'asc';

    protected array $sortable = ['name', 'parts_count'];

    public bool $showFlyout = false;

    public ?int $categoryId = null;

    public string $name = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function createCategory(): void
    {
        $this->reset(['categoryId', 'name']);
        $this->resetValidation();
        $this->showFlyout = true;
    }

    public function editCategory(int $id): void
    {
        $category = PartCategory::query()->findOrFail($id);
        $this->categoryId = $category->id;
        $this->name = $category->name;
        $this->resetValidation();
        $this->showFlyout = true;
    }

    public function saveCategory(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:80', Rule::unique('part_categories', 'name')->ignore($this->categoryId)],
        ]);

        $category = $this->categoryId ? PartCategory::query()->findOrFail($this->categoryId) : new PartCategory;
        $category->fill($data)->save();
        $this->showFlyout = false;
        $this->notifySuccess(__('Category saved.'));
    }

    public function deleteCategory(int $id): void
    {
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
        ];
    }
}; ?>

<div>
    <x-core::ui.page-header
        :title="__('Part categories')"
        :subtitle="__('Categories available on the incoming part form.')"
        :breadcrumbs="[
            admin_breadcrumb_home(),
            ['label' => __('Categories')],
        ]"
    >
        <x-slot:actions>
            <flux:button variant="primary" icon="plus" wire:click="createCategory">{{ __('Add category') }}</flux:button>
        </x-slot:actions>
    </x-core::ui.page-header>

    <div class="mb-4 admin-card p-3">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search category')" />
    </div>

    <div class="overflow-x-auto admin-card">
        <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
            <thead class="bg-[var(--admin-surface-muted)]">
                <tr>
                    <x-core::ui.sortable-th column="name" :label="__('Category')" />
                    <x-core::ui.sortable-th column="parts_count" :label="__('Parts')" />
                    <th></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse ($categories as $category)
                    <tr wire:key="category-{{ $category->id }}">
                        <td class="px-4 py-3 text-sm font-medium">{{ $category->name }}</td>
                        <td class="px-4 py-3 text-sm">{{ $category->parts_count }}</td>
                        <td class="px-4 py-3 text-right">
                            <x-core::ui.actions-menu>
                                <flux:menu.item icon="pencil-square" icon:variant="outline" wire:click="editCategory({{ $category->id }})">{{ __('Edit') }}</flux:menu.item>
                                <flux:menu.separator />
                                <x-core::ui.confirm-button as="menu-item" method="deleteCategory" :params="[$category->id]" />
                            </x-core::ui.actions-menu>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="px-4 py-12 text-center text-sm text-zinc-500">{{ __('No categories yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <x-core::ui.pagination :paginator="$categories" />

    <flux:modal wire:model="showFlyout" variant="flyout" class="md:min-w-[28rem] !rounded-s-3xl !ps-8 !pe-8">
        <form wire:submit="saveCategory" class="flex h-full flex-col gap-4">
            <flux:heading size="lg">{{ $categoryId ? __('Edit category') : __('Add category') }}</flux:heading>
            <div class="flex-1 space-y-3 overflow-y-auto pe-2">
                <flux:input wire:model="name" :label="__('Name')" required />
            </div>
            <div class="flex justify-end gap-2 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                <flux:button type="button" variant="ghost" wire:click="$set('showFlyout', false)">{{ __('Cancel') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
