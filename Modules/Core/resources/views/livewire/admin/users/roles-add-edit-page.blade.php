<?php

use App\Concerns\ManagesGrantedPermissions;
use App\Concerns\NotifiesWithSweetAlert;
use App\Models\Role;
use App\Models\User;
use App\Support\PermissionCatalog;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Spatie\Permission\PermissionRegistrar;

new #[Layout('core::layouts.app')] class extends Component {
    use ManagesGrantedPermissions;
    use NotifiesWithSweetAlert;

    public ?int $roleId = null;

    public bool $isEdit = false;

    public string $activeTab = 'permissions';

    public string $name = '';

    public string $description = '';

    public bool $is_active = true;

    /** @var list<string> */
    public array $grantedPermissions = [];

    public function mount(?Role $role = null): void
    {
        PermissionCatalog::ensurePermissionsExist();

        if ($role && $role->exists) {
            abort_if($role->name === 'admin', 403);

            $this->isEdit = true;
            $this->roleId = $role->id;
            $this->name = (string) $role->name;
            $this->description = (string) ($role->description ?? '');
            $this->is_active = (bool) $role->is_active;
            $this->setGrantedPermissions($role->permissions->pluck('name')->all());
        }
    }

    public function save(): void
    {
        $rules = [
            'name' => [
                'required',
                'string',
                'max:125',
                'regex:/^[a-z0-9_\-]+$/',
                Rule::unique('roles', 'name')->ignore($this->roleId)->where(fn ($q) => $q->where('guard_name', 'web')),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['boolean'],
            'grantedPermissions' => ['array'],
            'grantedPermissions.*' => ['string', Rule::in(PermissionCatalog::allPermissionNames())],
        ];

        if ($this->isEdit && in_array($this->name, ['manager', 'purchase', 'incoming'], true)) {
            // Seeded template names stay fixed.
            unset($rules['name']);
        }

        $this->validate($rules);

        if ($this->name === 'admin') {
            $this->addError('name', __('The admin role cannot be managed here.'));

            return;
        }

        PermissionCatalog::ensurePermissionsExist();

        if ($this->isEdit) {
            $role = Role::query()->editable()->findOrFail($this->roleId);
            $payload = [
                'description' => $this->description ?: null,
                'is_active' => $this->is_active,
            ];

            if (! in_array($role->name, ['manager', 'purchase', 'incoming'], true)) {
                $payload['name'] = $this->name;
            }

            $role->update($payload);
        } else {
            $role = Role::create([
                'name' => $this->name,
                'guard_name' => 'web',
                'description' => $this->description ?: null,
                'is_active' => $this->is_active,
            ]);
            $this->roleId = $role->id;
            $this->isEdit = true;
        }

        $names = array_values(array_intersect(
            PermissionCatalog::allPermissionNames(),
            $this->grantedPermissions,
        ));

        $role->syncPermissions($names);
        $this->applyRolePermissionsToUsers($role, $names);

        $this->dispatch(
            'SweetMessage',
            type: 'success',
            title: __('Success'),
            message: __('Role saved successfully.'),
            url: route('admin::users.roles.index'),
        );
    }

    /**
     * @param  list<string>  $names
     */
    private function applyRolePermissionsToUsers(Role $role, array $names): void
    {
        $types = match ($role->name) {
            'manager' => ['manager', 'sub_admin', 'staff'],
            'purchase' => ['purchase'],
            'incoming' => ['incoming'],
            default => [],
        };

        if ($types !== []) {
            User::query()
                ->whereIn('user_type', $types)
                ->each(function (User $user) use ($role, $names): void {
                    $user->syncRoles([$role->name]);
                    $user->syncPermissions($names);
                });
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}; ?>

<div class="w-full space-y-6">
    <x-core::ui.page-header
        :title="$isEdit ? __('Edit Role') : __('New Role')"
        :subtitle="__('Configure role details and permission scopes')"
        :breadcrumbs="[
            ['label' => __('Dashboard'), 'route' => 'admin::dashboard'],
            ['label' => __('Users'), 'route' => 'admin::users.list'],
            ['label' => __('Roles & Permissions'), 'route' => 'admin::users.roles.index'],
            ['label' => $isEdit ? __('Edit') : __('New')],
        ]"
    >
        <x-slot:actions>
            <flux:button icon="arrow-left" variant="ghost" size="sm" :href="route('admin::users.roles.index')" wire:navigate>
                {{ __('Back') }}
            </flux:button>
        </x-slot:actions>
    </x-core::ui.page-header>

    <form wire:submit="save" class="space-y-6">
        <div class="admin-card overflow-hidden">
            <div class="flex gap-1 border-b border-zinc-200 px-4 pt-3 dark:border-zinc-700">
                <button
                    type="button"
                    wire:click="$set('activeTab', 'permissions')"
                    @class([
                        'border-b-2 px-4 py-2 text-sm font-medium',
                        'border-[var(--admin-primary)] text-[var(--admin-primary)]' => $activeTab === 'permissions',
                        'border-transparent text-zinc-500 hover:text-zinc-700' => $activeTab !== 'permissions',
                    ])
                >
                    {{ __('Permissions') }}
                </button>
                <button
                    type="button"
                    wire:click="$set('activeTab', 'details')"
                    @class([
                        'border-b-2 px-4 py-2 text-sm font-medium',
                        'border-[var(--admin-primary)] text-[var(--admin-primary)]' => $activeTab === 'details',
                        'border-transparent text-zinc-500 hover:text-zinc-700' => $activeTab !== 'details',
                    ])
                >
                    {{ __('Role Details') }}
                </button>
            </div>

            <div class="px-6 py-6">
                @if ($activeTab === 'details')
                    <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
                        <flux:input
                            wire:model="name"
                            :label="__('Role name (slug)')"
                            :description="__('Lowercase letters, numbers, underscores. Example: warehouse')"
                            :disabled="$isEdit && in_array($name, ['manager', 'purchase', 'incoming'], true)"
                            required
                        />
                        <div class="flex items-end pb-1">
                            <flux:switch wire:model="is_active" :label="__('Active')" />
                        </div>
                        <div class="lg:col-span-2">
                            <flux:textarea wire:model="description" :label="__('Description')" rows="3" />
                        </div>
                    </div>
                @else
                    <x-core::users.permission-matrix />
                @endif
            </div>

            <div class="flex items-center justify-center gap-3 border-t border-zinc-200 bg-[var(--admin-surface-muted)] px-6 py-4 dark:border-zinc-700">
                <flux:button variant="ghost" :href="route('admin::users.roles.index')" wire:navigate>
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button variant="primary" type="submit" wire:loading.attr="disabled">
                    {{ __('Save Role') }}
                </flux:button>
            </div>
        </div>
    </form>
</div>
