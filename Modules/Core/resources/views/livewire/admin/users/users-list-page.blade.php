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
    public string $sortBy = 'id';
    public string $sortDirection = 'desc';

    protected array $sortable = ['id', 'name', 'email', 'user_type', 'is_active', 'created_at'];

    public function mount(): void
    {
        $this->search = (string) request('search', '');
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingRoleFilter(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function sort(string $column): void
    {
        if (! in_array($column, $this->sortable, true)) {
            return;
        }

        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'asc';
        }

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

    public function clearFilters(): void
    {
        $this->reset(['search', 'roleFilter', 'statusFilter']);
        $this->resetPage();
    }

    public function with(): array
    {
        $sortBy = in_array($this->sortBy, $this->sortable, true) ? $this->sortBy : 'id';
        $sortDirection = $this->sortDirection === 'asc' ? 'asc' : 'desc';

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
            ->orderBy($sortBy, $sortDirection)
            ->paginate($this->perPage);

        return compact('users');
    }
}; ?>

<div>
    {{-- Page Header --}}
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between admin-fade-in">
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

    {{-- Filters toolbar --}}
    <div class="mb-4 admin-card p-3 admin-fade-in admin-fade-in-delay-1">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center">
            <div class="min-w-0 flex-1">
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    placeholder="{{ __('Search by name, email, or username…') }}"
                    icon="magnifying-glass"
                />
            </div>

            <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:flex lg:shrink-0 lg:items-center">
                <flux:select wire:model.live="roleFilter" class="w-full lg:w-40">
                    <flux:select.option value="">{{ __('All Roles') }}</flux:select.option>
                    @foreach (array_merge(App\Models\User::ADMIN_ROLES, App\Models\User::USER_ROLES) as $key => $label)
                        <flux:select.option value="{{ $key }}">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model.live="statusFilter" class="w-full lg:w-36">
                    <flux:select.option value="">{{ __('All Status') }}</flux:select.option>
                    <flux:select.option value="1">{{ __('Active') }}</flux:select.option>
                    <flux:select.option value="0">{{ __('Inactive') }}</flux:select.option>
                </flux:select>

                <flux:select wire:model.live="perPage" class="col-span-2 w-full sm:col-span-1 lg:w-32">
                    <flux:select.option value="10">10 / page</flux:select.option>
                    <flux:select.option value="25">25 / page</flux:select.option>
                    <flux:select.option value="50">50 / page</flux:select.option>
                </flux:select>
            </div>
        </div>
    </div>

    {{-- Table --}}
    <div class="overflow-x-auto overflow-hidden admin-card admin-fade-in admin-fade-in-delay-2">
        <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
            <thead class="bg-[var(--admin-surface-muted)]">
                <tr>
                    @php
                        $headers = [
                            'id' => ['label' => '#', 'align' => 'left'],
                            'name' => ['label' => __('Name'), 'align' => 'left'],
                            'email' => ['label' => __('Email'), 'align' => 'left'],
                            'user_type' => ['label' => __('Role'), 'align' => 'left'],
                            'is_active' => ['label' => __('Status'), 'align' => 'center'],
                        ];
                    @endphp
                    @foreach ($headers as $column => $meta)
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400 {{ $meta['align'] === 'center' ? 'text-center' : 'text-left' }}">
                            <button
                                type="button"
                                wire:click="sort('{{ $column }}')"
                                class="inline-flex items-center gap-1 transition-colors hover:text-primary {{ $meta['align'] === 'center' ? 'justify-center' : '' }}"
                            >
                                <span>{{ $meta['label'] }}</span>
                                @if ($sortBy === $column)
                                    <flux:icon
                                        :name="$sortDirection === 'asc' ? 'chevron-up' : 'chevron-down'"
                                        class="size-3.5 text-primary"
                                    />
                                @else
                                    <flux:icon.chevron-up-down class="size-3.5 opacity-40" />
                                @endif
                            </button>
                        </th>
                    @endforeach
                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse ($users as $user)
                    <tr class="transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-800/40">
                        <td class="px-4 py-3 text-sm text-zinc-500 dark:text-zinc-400">{{ $user->id }}</td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <span class="flex h-8 w-8 items-center justify-center rounded-full bg-primary/15 text-xs font-semibold text-primary">
                                    {{ $user->initials() }}
                                </span>
                                <div>
                                    <p class="text-sm font-medium">{{ $user->name }} {{ $user->last_name }}</p>
                                    @if ($user->username)
                                        <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ '@' . $user->username }}</p>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-sm text-zinc-700 dark:text-zinc-300">{{ $user->email }}</td>
                        <td class="px-4 py-3">
                            @php
                                $allRoles = array_merge(App\Models\User::ADMIN_ROLES, App\Models\User::USER_ROLES);
                                $roleLabel = $allRoles[$user->user_type] ?? ucfirst($user->user_type ?? 'user');
                                $roleColor = match($user->user_type) {
                                    'admin'     => 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
                                    'sub_admin' => 'bg-orange-100 text-orange-700 dark:bg-orange-900/40 dark:text-orange-300',
                                    'staff'     => 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300',
                                    default     => 'bg-zinc-100 text-zinc-700 dark:bg-zinc-700 dark:text-zinc-300',
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
                                    <span class="inline-flex items-center gap-1 rounded-full bg-zinc-100 px-2.5 py-0.5 text-xs font-medium text-zinc-500 dark:bg-zinc-700 dark:text-zinc-400">
                                        <span class="h-1.5 w-1.5 rounded-full bg-zinc-400"></span> {{ __('Inactive') }}
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
                            <div class="flex flex-col items-center gap-2 text-zinc-400 dark:text-zinc-500">
                                <flux:icon.users class="h-10 w-10 opacity-40" />
                                <p class="text-sm">{{ __('No users found.') }}</p>
                                @if($search || $roleFilter || $statusFilter !== '')
                                    <flux:button variant="ghost" size="sm" wire:click="clearFilters">
                                        {{ __('Clear filters') }}
                                    </flux:button>
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
