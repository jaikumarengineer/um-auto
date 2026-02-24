<?php

use Livewire\Volt\Component;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;

new #[Layout('core::layouts.app')] class extends Component {
    use WithPagination;

    public string $search = '';
    public string $roleFilter = '';
    public string $statusFilter = '';
    public int $perPage = 10;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function deleteUser(int $id): void
    {
        $user = User::findOrFail($id);

        if ($user->id === auth()->id()) {
            session()->flash('error', 'You cannot delete your own account.');
            return;
        }

        $user->delete();
        session()->flash('success', 'User deleted successfully.');
    }

    public function toggleStatus(int $id): void
    {
        $user = User::findOrFail($id);
        $user->update(['is_active' => ! $user->is_active]);
    }

    public function with(): array
    {
        $users = User::query()
            ->when($this->search, fn ($q) =>
                $q->where(fn ($q) =>
                    $q->where('name', 'like', '%' . $this->search . '%')
                      ->orWhere('email', 'like', '%' . $this->search . '%')
                      ->orWhere('username', 'like', '%' . $this->search . '%')
                )
            )
            ->when($this->roleFilter, fn ($q) => $q->where('user_type', $this->roleFilter))
            ->when($this->statusFilter !== '', fn ($q) => $q->where('is_active', (bool) $this->statusFilter))
            ->latest()
            ->paginate($this->perPage);

        return compact('users');
    }
}; ?>

<div>
    {{-- Page Header --}}
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Users') }}</flux:heading>
            <flux:subheading>{{ __('Manage all registered users.') }}</flux:subheading>
        </div>
        <flux:button icon="plus" variant="primary" :href="route('admin::users.create')" wire:navigate>
            {{ __('Add User') }}
        </flux:button>
    </div>

    {{-- Flash Messages --}}
    @if (session('success'))
        <flux:callout variant="success" icon="check-circle" class="mb-4">{{ session('success') }}</flux:callout>
    @endif
    @if (session('error'))
        <flux:callout variant="danger" icon="x-circle" class="mb-4">{{ session('error') }}</flux:callout>
    @endif

    {{-- Filters --}}
    <div class="mb-4 flex flex-wrap items-center gap-3">
        <flux:input
            wire:model.live.debounce.300ms="search"
            placeholder="{{ __('Search by name or email…') }}"
            icon="magnifying-glass"
            class="w-64"
        />

        <flux:select wire:model.live="roleFilter" class="w-40">
            <flux:select.option value="">{{ __('All Roles') }}</flux:select.option>
            @foreach (array_merge(App\Models\User::ADMIN_ROLES, App\Models\User::USER_ROLES) as $key => $label)
                <flux:select.option value="{{ $key }}">{{ $label }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="statusFilter" class="w-36">
            <flux:select.option value="">{{ __('All Status') }}</flux:select.option>
            <flux:select.option value="1">{{ __('Active') }}</flux:select.option>
            <flux:select.option value="0">{{ __('Inactive') }}</flux:select.option>
        </flux:select>

        <flux:select wire:model.live="perPage" class="w-28">
            <flux:select.option value="10">10 / page</flux:select.option>
            <flux:select.option value="25">25 / page</flux:select.option>
            <flux:select.option value="50">50 / page</flux:select.option>
        </flux:select>
    </div>

    {{-- Table --}}
    <div class="overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700">
        <table class="min-w-full divide-y divide-neutral-200 dark:divide-neutral-700">
            <thead class="bg-neutral-50 dark:bg-neutral-800">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-neutral-500 dark:text-neutral-400">#</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-neutral-500 dark:text-neutral-400">{{ __('Name') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-neutral-500 dark:text-neutral-400">{{ __('Email') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-neutral-500 dark:text-neutral-400">{{ __('Role') }}</th>
                    <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-neutral-500 dark:text-neutral-400">{{ __('Status') }}</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-neutral-500 dark:text-neutral-400">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-neutral-200 bg-white dark:divide-neutral-700 dark:bg-neutral-900">
                @forelse ($users as $user)
                    <tr class="hover:bg-neutral-50 dark:hover:bg-neutral-800/50 transition-colors">
                        <td class="px-4 py-3 text-sm text-neutral-500 dark:text-neutral-400">{{ $user->id }}</td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <span class="flex h-8 w-8 items-center justify-center rounded-full bg-indigo-100 text-xs font-semibold text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300">
                                    {{ $user->initials() }}
                                </span>
                                <div>
                                    <p class="text-sm font-medium text-neutral-900 dark:text-white">{{ $user->name }} {{ $user->last_name }}</p>
                                    <p class="text-xs text-neutral-500 dark:text-neutral-400">@{{ $user->username }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-sm text-neutral-700 dark:text-neutral-300">{{ $user->email }}</td>
                        <td class="px-4 py-3">
                            @php
                                $allRoles = array_merge(App\Models\User::ADMIN_ROLES, App\Models\User::USER_ROLES);
                                $roleLabel = $allRoles[$user->user_type] ?? ucfirst($user->user_type ?? 'user');
                                $roleColor = match($user->user_type) {
                                    'admin'     => 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
                                    'sub_admin' => 'bg-orange-100 text-orange-700 dark:bg-orange-900/40 dark:text-orange-300',
                                    'staff'     => 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300',
                                    default     => 'bg-neutral-100 text-neutral-700 dark:bg-neutral-700 dark:text-neutral-300',
                                };
                            @endphp
                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $roleColor }}">
                                {{ $roleLabel }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <button wire:click="toggleStatus({{ $user->id }})" class="focus:outline-none" title="Toggle status">
                                @if($user->is_active)
                                    <span class="inline-flex items-center gap-1 rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-700 dark:bg-green-900/40 dark:text-green-300">
                                        <span class="h-1.5 w-1.5 rounded-full bg-green-500"></span> {{ __('Active') }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 rounded-full bg-neutral-100 px-2.5 py-0.5 text-xs font-medium text-neutral-500 dark:bg-neutral-700 dark:text-neutral-400">
                                        <span class="h-1.5 w-1.5 rounded-full bg-neutral-400"></span> {{ __('Inactive') }}
                                    </span>
                                @endif
                            </button>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <flux:button
                                    size="sm"
                                    variant="ghost"
                                    icon="pencil-square"
                                    :href="route('admin::users.edit', $user->id)"
                                    wire:navigate
                                    title="Edit"
                                />
                                <flux:button
                                    size="sm"
                                    variant="ghost"
                                    icon="trash"
                                    wire:click="deleteUser({{ $user->id }})"
                                    wire:confirm="{{ __('Are you sure you want to delete this user?') }}"
                                    class="text-red-500 hover:text-red-700"
                                    title="Delete"
                                />
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-12 text-center">
                            <div class="flex flex-col items-center gap-2 text-neutral-400 dark:text-neutral-500">
                                <flux:icon.users class="h-10 w-10 opacity-40" />
                                <p class="text-sm">{{ __('No users found.') }}</p>
                                @if($search)
                                    <flux:button variant="ghost" size="sm" wire:click="$set('search', '')">{{ __('Clear search') }}</flux:button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    @if ($users->hasPages())
        <div class="mt-4">
            {{ $users->links() }}
        </div>
    @endif
</div>
