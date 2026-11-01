<?php

use App\Concerns\NotifiesWithSweetAlert;
use App\Models\Role;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('core::layouts.app')] class extends Component {
    use NotifiesWithSweetAlert;
    use WithPagination;

    public string $search = '';

    public int $perPage = 10;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function toggleStatus(int $id): void
    {
        $role = Role::query()->editable()->findOrFail($id);
        $role->update(['is_active' => ! $role->is_active]);
        $this->notifySuccess(__('Role status updated.'));
    }

    public function with(): array
    {
        $roles = Role::query()
            ->editable()
            ->withCount('permissions')
            ->when($this->search !== '', function ($query) {
                $term = '%'.$this->search.'%';
                $query->where(function ($query) use ($term) {
                    $query->where('name', 'like', $term)
                        ->orWhere('description', 'like', $term);
                });
            })
            ->orderBy('name')
            ->paginate($this->perPage);

        return compact('roles');
    }
}; ?>

<div>
    <x-core::ui.page-header
        class="admin-fade-in"
        :title="__('Roles & Permissions')"
        :subtitle="__('Manage role templates and their permission scopes')"
        :breadcrumbs="[
            ['label' => __('Dashboard'), 'route' => 'admin::dashboard'],
            ['label' => __('Users'), 'route' => 'admin::users.list'],
            ['label' => __('Roles & Permissions')],
        ]"
    >
        <x-slot:actions>
            <flux:button variant="ghost" icon="arrow-left" :href="route('admin::users.list')" wire:navigate>
                {{ __('Back to users') }}
            </flux:button>
            <flux:button icon="plus" variant="primary" :href="route('admin::users.roles.create')" wire:navigate>
                {{ __('Add Role') }}
            </flux:button>
        </x-slot:actions>
    </x-core::ui.page-header>

    <div class="mb-4 admin-card p-3">
        <flux:input
            wire:model.live.debounce.300ms="search"
            icon="magnifying-glass"
            :placeholder="__('Search roles…')"
        />
    </div>

    <div class="overflow-x-auto admin-card">
        <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
            <thead class="bg-[var(--admin-surface-muted)]">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-zinc-500">{{ __('Role') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-zinc-500">{{ __('Description') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-zinc-500">{{ __('Permissions') }}</th>
                    <th class="px-4 py-3 text-center text-xs font-semibold text-zinc-500">{{ __('Status') }}</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-zinc-500">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse ($roles as $role)
                    <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/40" wire:key="role-{{ $role->id }}">
                        <td class="px-4 py-3 text-sm font-medium">{{ $role->displayName() }}</td>
                        <td class="px-4 py-3 text-sm text-zinc-600 dark:text-zinc-300">{{ $role->description ?: '—' }}</td>
                        <td class="px-4 py-3 text-sm tabular-nums">{{ $role->permissions_count }}</td>
                        <td class="px-4 py-3 text-center">
                            <button type="button" wire:click="toggleStatus({{ $role->id }})" class="inline-flex">
                                @if ($role->is_active)
                                    <span class="inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-700 dark:bg-green-900/40 dark:text-green-300">
                                        {{ __('Active') }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-zinc-100 px-2.5 py-0.5 text-xs font-medium text-zinc-600 dark:bg-zinc-700 dark:text-zinc-300">
                                        {{ __('Inactive') }}
                                    </span>
                                @endif
                            </button>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <flux:button
                                size="sm"
                                variant="ghost"
                                icon="pencil-square"
                                :href="route('admin::users.roles.edit', $role)"
                                wire:navigate
                            >
                                {{ __('Edit') }}
                            </flux:button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-12 text-center text-sm text-zinc-500">{{ __('No roles found.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <x-core::ui.pagination :paginator="$roles" />
</div>
