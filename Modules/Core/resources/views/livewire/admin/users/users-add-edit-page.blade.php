<?php

use App\Concerns\ManagesGrantedPermissions;
use App\Models\Role;
use App\Models\User;
use App\Support\PermissionCatalog;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Modules\Quality\Support\QualityAlertCatalog;
use Spatie\Permission\Models\Permission;

new #[Layout('core::layouts.app')] class extends Component {
    use ManagesGrantedPermissions;

    public ?int $userId = null;

    public bool $isEdit = false;

    public string $name = '';

    public string $last_name = '';

    public string $username = '';

    public string $email = '';

    public string $phone = '';

    public string $password = '';

    public string $password_confirmation = '';

    public string $country = '';

    public string $state = '';

    public string $city = '';

    public string $zip_code = '';

    public string $address = '';

    public string $user_type = 'user';

    public bool $is_active = true;

    public ?string $image_path = null;

    public $image = null;

    public bool $email_alerts_enabled = false;

    /** @var list<string> */
    public array $departmentChoices = [];

    public bool $departmentsAll = true;

    /** @var list<string> */
    public array $statusChoices = [];

    public bool $statusesAll = true;

    /** @var list<string> */
    public array $reportChoices = [];

    public bool $reportsAll = true;

    /** @var list<string> */
    public array $summaryChoices = [];

    public bool $summariesAll = true;

    /**
     * Checked permission names (Spatie permission strings).
     *
     * @var list<string>
     */
    public array $grantedPermissions = [];

    /**
     * Draft permissions while the customize flyout is open.
     *
     * @var list<string>
     */
    public array $draftPermissions = [];

    public string $copyFromRole = '';

    public bool $showPermissionsFlyout = false;

    public function mount(?int $user_id = null): void
    {
        PermissionCatalog::ensurePermissionsExist();

        $this->hydrateAlertGroup('departments', ['all'], array_keys(QualityAlertCatalog::DEPARTMENTS));
        $this->hydrateAlertGroup('statuses', ['all'], array_keys(QualityAlertCatalog::STATUSES));
        $this->hydrateAlertGroup('reports', ['all'], array_keys(QualityAlertCatalog::REPORTS));
        $this->hydrateAlertGroup('summaries', ['all'], array_keys(QualityAlertCatalog::SUMMARIES));

        if ($user_id) {
            $user = User::findOrFail($user_id);
            $this->isEdit = true;
            $this->userId = $user->id;
            $this->name = (string) $user->name;
            $this->last_name = (string) ($user->last_name ?? '');
            $this->username = (string) ($user->username ?? '');
            $this->email = (string) $user->email;
            $this->phone = (string) ($user->phone ?? '');
            $this->country = (string) ($user->country ?? '');
            $this->state = (string) ($user->state ?? '');
            $this->city = (string) ($user->city ?? '');
            $this->zip_code = (string) ($user->zip_code ?? '');
            $this->address = (string) ($user->address_line_1 ?? '');
            $this->user_type = (string) ($user->user_type ?? 'user');
            $this->is_active = (bool) $user->is_active;
            $this->image_path = $user->image;
            $this->image = $user->image;

            $prefs = $user->emailAlertPreferences();
            $this->email_alerts_enabled = $prefs['enabled'];
            $this->hydrateAlertGroup('departments', $prefs['departments'], array_keys(QualityAlertCatalog::DEPARTMENTS));
            $this->hydrateAlertGroup('statuses', $prefs['statuses'], array_keys(QualityAlertCatalog::STATUSES));
            $this->hydrateAlertGroup('reports', $prefs['reports'], array_keys(QualityAlertCatalog::REPORTS));
            $this->hydrateAlertGroup('summaries', $prefs['summaries'], array_keys(QualityAlertCatalog::SUMMARIES));
            $this->loadGrantedPermissionsFromUser($user);
        } else {
            $this->resetGrantedPermissionsForRole('manager');
        }
    }

    public function updatedUserType($value): void
    {
        $value = (string) $value;

        if ($value === 'admin') {
            $this->grantedPermissions = [];
            $this->copyFromRole = '';
            $this->closePermissionsFlyout();

            return;
        }

        $this->copyFromRole = '';
        $role = PermissionCatalog::roleForUserType($value) ?? $value;
        $this->resetGrantedPermissionsForRole($role);
    }

    public function updatedCopyFromRole($value): void
    {
        $value = (string) $value;

        if ($value === '' || $this->user_type === 'admin') {
            return;
        }

        $this->setGrantedPermissions(PermissionCatalog::permissionsForRole($value));
    }

    public function openPermissionsFlyout(): void
    {
        if ($this->user_type === 'admin') {
            return;
        }

        $this->draftPermissions = $this->grantedPermissions;
        $this->showPermissionsFlyout = true;
    }

    public function closePermissionsFlyout(): void
    {
        $this->showPermissionsFlyout = false;
        $this->draftPermissions = [];
    }

    public function savePermissionsFlyout(): void
    {
        $this->setGrantedPermissions($this->draftPermissions);
        $this->showPermissionsFlyout = false;
        $this->draftPermissions = [];
    }

    public function selectAllDraftPermissions(): void
    {
        $this->draftPermissions = PermissionCatalog::allPermissionNames();
    }

    public function clearDraftPermissions(): void
    {
        $this->draftPermissions = [];
    }

    public function toggleDraftPermission(string $name): void
    {
        if (! in_array($name, PermissionCatalog::allPermissionNames(), true)) {
            return;
        }

        $current = collect($this->draftPermissions);

        if ($current->contains($name)) {
            $this->draftPermissions = $current
                ->reject(fn (string $item) => $item === $name)
                ->values()
                ->all();

            return;
        }

        $this->draftPermissions = $current->push($name)->unique()->values()->all();
    }

    public function toggleDraftModule(string $module): void
    {
        $names = collect(PermissionCatalog::actions())
            ->map(fn (string $action) => PermissionCatalog::name($module, $action))
            ->all();

        $current = collect($this->draftPermissions);
        $allOn = collect($names)->every(fn (string $name) => $current->contains($name));

        if ($allOn) {
            $this->draftPermissions = $current->reject(fn (string $name) => in_array($name, $names, true))->values()->all();

            return;
        }

        $this->draftPermissions = $current->merge($names)->unique()->values()->all();
    }

    public function updatedName(): void
    {
        if (! $this->isEdit) {
            $this->username = generateUserName($this->name);
        }
    }

    public function updatedDepartmentsAll(bool $value): void
    {
        $this->applyAlertAllToggle('departments', $value, array_keys(QualityAlertCatalog::DEPARTMENTS));
    }

    public function updatedDepartmentChoices(): void
    {
        $this->syncAlertAllFlag('departments', array_keys(QualityAlertCatalog::DEPARTMENTS));
    }

    public function updatedStatusesAll(bool $value): void
    {
        $this->applyAlertAllToggle('statuses', $value, array_keys(QualityAlertCatalog::STATUSES));
    }

    public function updatedStatusChoices(): void
    {
        $this->syncAlertAllFlag('statuses', array_keys(QualityAlertCatalog::STATUSES));
    }

    public function updatedReportsAll(bool $value): void
    {
        $this->applyAlertAllToggle('reports', $value, array_keys(QualityAlertCatalog::REPORTS));
    }

    public function updatedReportChoices(): void
    {
        $this->syncAlertAllFlag('reports', array_keys(QualityAlertCatalog::REPORTS));
    }

    public function updatedSummariesAll(bool $value): void
    {
        $this->applyAlertAllToggle('summaries', $value, array_keys(QualityAlertCatalog::SUMMARIES));
    }

    public function updatedSummaryChoices(): void
    {
        $this->syncAlertAllFlag('summaries', array_keys(QualityAlertCatalog::SUMMARIES));
    }

    public function allowedRoles(): array
    {
        $roles = array_keys(User::assignableRoles());

        if ($this->isEdit && $this->user_type !== '' && ! in_array($this->user_type, $roles, true)) {
            $roles[] = $this->user_type;
        }

        return $roles;
    }

    public function save(): void
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique(User::class, 'email')->ignore($this->userId),
            ],
            'username' => [
                'required',
                'string',
                'max:255',
                Rule::unique(User::class, 'username')->ignore($this->userId),
            ],
            'phone' => ['nullable', 'string', 'max:40'],
            'country' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'zip_code' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:5000'],
            'user_type' => ['required', 'string', Rule::in($this->allowedRoles())],
            'is_active' => ['boolean'],
            'image' => ['nullable', 'string', 'max:500'],
            'email_alerts_enabled' => ['boolean'],
            'departmentChoices' => ['array'],
            'departmentChoices.*' => ['string', Rule::in(array_keys(QualityAlertCatalog::DEPARTMENTS))],
            'statusChoices' => ['array'],
            'statusChoices.*' => ['string', Rule::in(array_keys(QualityAlertCatalog::STATUSES))],
            'reportChoices' => ['array'],
            'reportChoices.*' => ['string', Rule::in(array_keys(QualityAlertCatalog::REPORTS))],
            'summaryChoices' => ['array'],
            'summaryChoices.*' => ['string', Rule::in(array_keys(QualityAlertCatalog::SUMMARIES))],
        ];

        if (! $this->isEdit || $this->password !== '') {
            $rules['password'] = ['required', 'string', Password::defaults(), 'confirmed'];
        }

        $this->validate($rules);

        $payload = [
            'name' => $this->name,
            'last_name' => $this->last_name ?: null,
            'username' => $this->username,
            'email' => $this->email,
            'phone' => $this->phone ?: null,
            'country' => $this->country ?: null,
            'state' => $this->state ?: null,
            'city' => $this->city ?: null,
            'zip_code' => $this->zip_code ?: null,
            'address_line_1' => $this->address ?: null,
            'user_type' => $this->user_type,
            'is_active' => $this->is_active,
            'email_alert_preferences' => QualityAlertCatalog::normalize([
                'enabled' => $this->email_alerts_enabled,
                'departments' => QualityAlertCatalog::collapseFromUi($this->departmentChoices, array_keys(QualityAlertCatalog::DEPARTMENTS)),
                'statuses' => QualityAlertCatalog::collapseFromUi($this->statusChoices, array_keys(QualityAlertCatalog::STATUSES)),
                'reports' => QualityAlertCatalog::collapseFromUi($this->reportChoices, array_keys(QualityAlertCatalog::REPORTS)),
                'summaries' => QualityAlertCatalog::collapseFromUi($this->summaryChoices, array_keys(QualityAlertCatalog::SUMMARIES)),
            ]),
        ];

        if (is_string($this->image) && filled($this->image)) {
            $payload['image'] = $this->image;
        }

        if ($this->password !== '') {
            $payload['password'] = Hash::make($this->password);
        }

        if ($this->isEdit) {
            $user = User::findOrFail($this->userId);
            $user->update($payload);
            $message = __('User updated successfully.');
        } else {
            $payload['password'] = $payload['password'] ?? Hash::make($this->password);
            $user = User::create($payload);
            $this->userId = $user->id;
            $this->isEdit = true;
            $this->password = '';
            $this->password_confirmation = '';
            $message = __('User created successfully.');
        }

        $this->syncSelectedPermissions($user->fresh());

        $this->dispatch(
            'SweetMessage',
            type: 'success',
            title: __('Success'),
            message: $message,
            url: route('admin::users.list'),
        );
    }

    /**
     * @param  list<string>  $stored
     * @param  list<string>  $particulars
     */
    private function hydrateAlertGroup(string $group, array $stored, array $particulars): void
    {
        $choices = QualityAlertCatalog::expandForUi($stored, $particulars);
        $all = $stored === ['all'] || ($particulars !== [] && count($choices) === count($particulars));

        match ($group) {
            'departments' => [$this->departmentChoices, $this->departmentsAll] = [$choices, $all],
            'statuses' => [$this->statusChoices, $this->statusesAll] = [$choices, $all],
            'reports' => [$this->reportChoices, $this->reportsAll] = [$choices, $all],
            'summaries' => [$this->summaryChoices, $this->summariesAll] = [$choices, $all],
            default => null,
        };
    }

    /**
     * @param  list<string>  $particulars
     */
    private function applyAlertAllToggle(string $group, bool $value, array $particulars): void
    {
        $choices = match ($group) {
            'departments' => $this->departmentChoices,
            'statuses' => $this->statusChoices,
            'reports' => $this->reportChoices,
            'summaries' => $this->summaryChoices,
            default => [],
        };

        $isComplete = $particulars !== []
            && count(array_intersect($choices, $particulars)) === count($particulars);

        if ($value) {
            if (! $isComplete) {
                $this->setAlertChoices($group, $particulars);
            }

            return;
        }

        if ($isComplete) {
            $this->setAlertChoices($group, []);
        }
    }

    /**
     * @param  list<string>  $particulars
     */
    private function syncAlertAllFlag(string $group, array $particulars): void
    {
        $choices = match ($group) {
            'departments' => $this->departmentChoices,
            'statuses' => $this->statusChoices,
            'reports' => $this->reportChoices,
            'summaries' => $this->summaryChoices,
            default => [],
        };

        $all = $particulars !== []
            && count(array_intersect($choices, $particulars)) === count($particulars);

        match ($group) {
            'departments' => $this->departmentsAll = $all,
            'statuses' => $this->statusesAll = $all,
            'reports' => $this->reportsAll = $all,
            'summaries' => $this->summariesAll = $all,
            default => null,
        };
    }

    /**
     * @param  list<string>  $choices
     */
    private function setAlertChoices(string $group, array $choices): void
    {
        match ($group) {
            'departments' => $this->departmentChoices = array_values($choices),
            'statuses' => $this->statusChoices = array_values($choices),
            'reports' => $this->reportChoices = array_values($choices),
            'summaries' => $this->summaryChoices = array_values($choices),
            default => null,
        };
    }

    private function resetGrantedPermissionsForRole(string $role): void
    {
        $this->setGrantedPermissions(PermissionCatalog::permissionsForRole($role));
    }

    private function loadGrantedPermissionsFromUser(User $user): void
    {
        if ($user->isUnrestrictedAdmin()) {
            $this->grantedPermissions = [];

            return;
        }

        $granted = [];

        foreach (PermissionCatalog::allPermissionNames() as $name) {
            if ($user->hasPermissionTo($name)) {
                $granted[] = $name;
            }
        }

        $this->setGrantedPermissions($granted);
    }

    private function syncSelectedPermissions(User $user): void
    {
        if ($user->isUnrestrictedAdmin()) {
            Role::findOrCreate('admin', 'web');
            $user->syncRoles(['admin']);
            $user->syncPermissions([]);

            return;
        }

        PermissionCatalog::ensurePermissionsExist();

        $names = array_values(array_intersect(
            PermissionCatalog::allPermissionNames(),
            $this->grantedPermissions,
        ));

        foreach ($names as $name) {
            Permission::findOrCreate($name, 'web');
        }

        $role = PermissionCatalog::roleForUserType((string) $user->user_type);
        if ($role) {
            Role::findOrCreate($role, 'web');
            $user->syncRoles([$role]);
        }

        $user->syncPermissions($names);
    }

    public function with(): array
    {
        $copyRoleOptions = Role::query()
            ->editable()
            ->active()
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (Role $role) => [$role->name => $role->displayName()])
            ->all();

        $permissionRoleLabel = $copyRoleOptions[$this->copyFromRole]
            ?? (User::assignableRoles()[$this->user_type] ?? str($this->user_type)->title()->toString());

        $totalPermissions = count(PermissionCatalog::allPermissionNames());
        $selectedPermissions = count(array_intersect(
            PermissionCatalog::allPermissionNames(),
            $this->grantedPermissions,
        ));

        return compact('copyRoleOptions', 'permissionRoleLabel', 'totalPermissions', 'selectedPermissions');
    }
}; ?>

@php
    $roleOptions = User::assignableRoles();

    if ($isEdit && $user_type !== '' && ! array_key_exists($user_type, $roleOptions)) {
        $roleOptions = [$user_type => (User::allRoleLabels()[$user_type] ?? ucfirst($user_type))] + $roleOptions;
    }

    $avatarSrc = media_url($image_path);

    $departmentOptions = QualityAlertCatalog::departmentLabels();
    $statusOptions = QualityAlertCatalog::statusLabels();
    $reportOptions = QualityAlertCatalog::reportLabels();
    $summaryOptions = QualityAlertCatalog::summaryLabels();
@endphp

<div class="w-full space-y-6">
    <x-core::ui.page-header
        :title="$isEdit ? __('Edit Website User') : __('New Website User')"
        :subtitle="__('Here you can add or edit the details of website user.')"
        :breadcrumbs="[
            ['label' => __('Dashboard'), 'route' => 'admin::dashboard'],
            ['label' => __('Users'), 'route' => 'admin::users.list'],
            ['label' => $isEdit ? __('Edit') : __('New')],
        ]"
    >
        <x-slot:actions>
            <flux:button
                icon="arrow-left"
                variant="ghost"
                size="sm"
                :href="route('admin::users.list')"
                wire:navigate
            >
                {{ __('Back') }}
            </flux:button>
        </x-slot:actions>
    </x-core::ui.page-header>

    <form wire:submit="save" class="w-full space-y-6">
        <div class="admin-card overflow-hidden">
            <div class="grid grid-cols-1 gap-5 px-6 py-6 lg:grid-cols-2">
                {{-- Avatar --}}
                <div class="lg:col-span-2">
                    <div class="w-full lg:w-2/5">
                        @if (! $avatarSrc)
                            <div class="mb-2 flex items-center gap-3">
                                <div class="flex size-12 items-center justify-center rounded-full bg-[color-mix(in_srgb,var(--admin-primary)_15%,white)] text-sm font-semibold text-[var(--admin-primary)] shadow-sm">
                                    {{ strtoupper(substr($name ?: 'U', 0, 1)) }}{{ strtoupper(substr($last_name ?: '', 0, 1)) }}
                                </div>
                                <p class="text-xs text-zinc-500">{{ __('No avatar uploaded yet') }}</p>
                            </div>
                        @endif
                        <x-core::forms.filepond
                            wire:model="image"
                            model="image"
                            folder="avatars"
                            :label="__('Avatar Image')"
                            :description="__('Dimension: 500 × 500 pixels')"
                            :accepted-file-types="['image/png', 'image/jpeg', 'image/webp']"
                            :preview="$avatarSrc"
                            shape="square"
                            circle
                            max-height="6rem"
                        />
                    </div>
                </div>

                <flux:input wire:model.live="name" :label="__('First Name')" required autofocus autocomplete="given-name" />
                <flux:input wire:model.live="last_name" :label="__('Last Name')" autocomplete="family-name" />
                <div class="lg:col-span-2">
                <flux:input wire:model="username" :label="__('Username')" required autocomplete="username" />
                </div>
                <flux:input wire:model="email" type="email" :label="__('Email')" required autocomplete="email" />
                <x-core::forms.phone wire:model="phone" :label="__('Phone')" />

                <flux:input
                    wire:model="password"
                    type="password"
                    viewable
                    :label="__('Password')"
                    :required="! $isEdit"
                    :description="$isEdit ? __('Leave blank if you do not want to change the password.') : null"
                    autocomplete="new-password"
                />
                <flux:input
                    wire:model="password_confirmation"
                    type="password"
                    viewable
                    :label="__('Retype password')"
                    :description="$isEdit ? __('Leave blank if you do not want to change the password.') : null"
                    :required="! $isEdit"
                    autocomplete="new-password"
                />

                <flux:input wire:model="country" :label="__('Country')" />
                <flux:input wire:model="state" :label="__('State')" />
                <flux:input wire:model="city" :label="__('City')" />
                <flux:input wire:model="zip_code" :label="__('Zipcode')" />

                <div class="lg:col-span-2">
                    <flux:textarea wire:model="address" :label="__('Address')" rows="3" />
                </div>

                <div class="lg:col-span-2">
                    <x-core::forms.select2
                        wire:model="user_type"
                        :label="__('Role')"
                        :options="$roleOptions"
                        :placeholder="__('Select role…')"
                    />
                </div>
                <div class="flex items-end pb-1">
                    <flux:switch wire:model="is_active" :label="__('Status (Active)')" />
                </div>
            </div>
        </div>

        @if ($user_type !== 'admin')
            <div class="admin-card overflow-hidden">
                <div class="space-y-2 border-b border-zinc-200 px-6 py-6 sm:px-8 dark:border-zinc-700">
                    <flux:heading size="lg">{{ __('Roles & Permission') }}</flux:heading>
                    <flux:subheading>
                        {{ __('Pick a role template for defaults, then customize individual permissions. Settings stay Admin-only.') }}
                    </flux:subheading>
                </div>
                <div class="space-y-4 px-6 py-6 sm:px-8">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                        <div class="min-w-0 flex-1">
                            <flux:select wire:model.live="copyFromRole" :label="__('Permission template')">
                                <flux:select.option value="">{{ __('Use selected role defaults') }}</flux:select.option>
                                @foreach ($copyRoleOptions as $value => $label)
                                    <flux:select.option :value="$value">{{ $label }}</flux:select.option>
                                @endforeach
                            </flux:select>
                        </div>
                        <flux:button
                            type="button"
                            variant="primary"
                            icon="adjustments-horizontal"
                            wire:click="openPermissionsFlyout"
                            class="shrink-0"
                        >
                            {{ __('Customize') }}
                        </flux:button>
                    </div>
                    <p class="text-sm text-zinc-500">
                        {{ __(':selected of :total permissions selected', [
                            'selected' => $selectedPermissions,
                            'total' => $totalPermissions,
                        ]) }}
                    </p>
                </div>
            </div>
        @endif

        {{-- Email alerts (admin-managed) --}}
        <div class="admin-card overflow-hidden">
            <div class="border-b border-zinc-200 px-6 py-4 dark:border-zinc-700">
                <flux:heading size="lg">{{ __('Email alerts') }}</flux:heading>
                <flux:subheading>
                    {{ __('Only a master admin can choose what this user receives. Pick all, one, or several departments, then statuses and reports. Summaries are day wise, weekly, and monthwise.') }}
                    <a href="{{ route('admin::settings.email-alerts') }}" class="underline" wire:navigate>{{ __('System switches live under Settings.') }}</a>
                </flux:subheading>
            </div>

            <div class="space-y-6 px-6 py-6">
                <flux:switch wire:model.live="email_alerts_enabled" :label="__('Send quality email alerts to this user')" />

                @if ($email_alerts_enabled)
                    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2 xl:grid-cols-4">
                        <x-core::forms.alert-checks :legend="__('Departments')" :hint="__('All, one department, or both.')">
                            <label class="flex cursor-pointer items-center gap-2.5 text-sm text-zinc-700 dark:text-zinc-300">
                                <input type="checkbox" wire:model.live="departmentsAll" class="size-4 shrink-0 rounded border-zinc-300 text-[var(--admin-primary)] focus:ring-[var(--admin-primary)]">
                                <span class="font-medium">{{ __('All departments') }}</span>
                            </label>
                            @foreach ($departmentOptions as $value => $label)
                                <label class="flex cursor-pointer items-center gap-2.5 text-sm text-zinc-700 dark:text-zinc-300">
                                    <input type="checkbox" value="{{ $value }}" wire:model.live="departmentChoices" class="size-4 shrink-0 rounded border-zinc-300 text-[var(--admin-primary)] focus:ring-[var(--admin-primary)]">
                                    <span>{{ $label }}</span>
                                </label>
                            @endforeach
                        </x-core::forms.alert-checks>

                        <x-core::forms.alert-checks :legend="__('Status')" :hint="__('Overdue, pending, and completed.')">
                            <label class="flex cursor-pointer items-center gap-2.5 text-sm text-zinc-700 dark:text-zinc-300">
                                <input type="checkbox" wire:model.live="statusesAll" class="size-4 shrink-0 rounded border-zinc-300 text-[var(--admin-primary)] focus:ring-[var(--admin-primary)]">
                                <span class="font-medium">{{ __('All statuses') }}</span>
                            </label>
                            @foreach ($statusOptions as $value => $label)
                                <label class="flex cursor-pointer items-center gap-2.5 text-sm text-zinc-700 dark:text-zinc-300">
                                    <input type="checkbox" value="{{ $value }}" wire:model.live="statusChoices" class="size-4 shrink-0 rounded border-zinc-300 text-[var(--admin-primary)] focus:ring-[var(--admin-primary)]">
                                    <span>{{ $label }}</span>
                                </label>
                            @endforeach
                        </x-core::forms.alert-checks>

                        <x-core::forms.alert-checks :legend="__('Reports')" :hint="__('PO vs PI and incoming inspection reports.')">
                            <label class="flex cursor-pointer items-center gap-2.5 text-sm text-zinc-700 dark:text-zinc-300">
                                <input type="checkbox" wire:model.live="reportsAll" class="size-4 shrink-0 rounded border-zinc-300 text-[var(--admin-primary)] focus:ring-[var(--admin-primary)]">
                                <span class="font-medium">{{ __('All reports') }}</span>
                            </label>
                            @foreach ($reportOptions as $value => $label)
                                <label class="flex cursor-pointer items-center gap-2.5 text-sm text-zinc-700 dark:text-zinc-300">
                                    <input type="checkbox" value="{{ $value }}" wire:model.live="reportChoices" class="size-4 shrink-0 rounded border-zinc-300 text-[var(--admin-primary)] focus:ring-[var(--admin-primary)]">
                                    <span>{{ $label }}</span>
                                </label>
                            @endforeach
                        </x-core::forms.alert-checks>

                        <x-core::forms.alert-checks :legend="__('Summaries')" :hint="__('Day wise, weekly, and monthwise.')">
                            <label class="flex cursor-pointer items-center gap-2.5 text-sm text-zinc-700 dark:text-zinc-300">
                                <input type="checkbox" wire:model.live="summariesAll" class="size-4 shrink-0 rounded border-zinc-300 text-[var(--admin-primary)] focus:ring-[var(--admin-primary)]">
                                <span class="font-medium">{{ __('All summaries') }}</span>
                            </label>
                            @foreach ($summaryOptions as $value => $label)
                                <label class="flex cursor-pointer items-center gap-2.5 text-sm text-zinc-700 dark:text-zinc-300">
                                    <input type="checkbox" value="{{ $value }}" wire:model.live="summaryChoices" class="size-4 shrink-0 rounded border-zinc-300 text-[var(--admin-primary)] focus:ring-[var(--admin-primary)]">
                                    <span>{{ $label }}</span>
                                </label>
                            @endforeach
                        </x-core::forms.alert-checks>
                    </div>
                @endif
            </div>

            <div class="flex items-center justify-center gap-3 border-t border-zinc-200 bg-[var(--admin-surface-muted)] px-6 py-4 dark:border-zinc-700">
                <flux:button variant="ghost" :href="route('admin::users.list')" wire:navigate>
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button variant="primary" type="submit" wire:loading.attr="disabled">
                    {{ __('Save User') }}
                </flux:button>
            </div>
        </div>
    </form>

    @if ($user_type !== 'admin')
        <x-core::users.permission-flyout :title="$permissionRoleLabel" />
    @endif
</div>
