<?php

use Livewire\Volt\Component;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;

new #[Layout('core::layouts.app')] class extends Component {

    public ?int $userId = null;
    public bool $isEdit  = false;

    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate('nullable|string|max:255')]
    public ?string $last_name = null;

    #[Validate('required|email|max:255')]
    public string $email = '';

    #[Validate('nullable|string|max:30')]
    public ?string $phone = null;

    public string $user_type = 'user';

    public bool $is_active = true;

    public string $password = '';
    public string $password_confirmation = '';

    public function mount(?int $user_id = null): void
    {
        if ($user_id) {
            $this->isEdit  = true;
            $this->userId  = $user_id;
            $user = User::findOrFail($user_id);

            $this->name                = $user->name ?? '';
            $this->last_name           = $user->last_name;
            $this->email               = $user->email;
            $this->phone               = $user->phone;
            $this->user_type           = $user->user_type ?? 'user';
            $this->is_active           = (bool) $user->is_active;
        }
    }

    public function save(): void
    {
        $rules = [
            'name'      => 'required|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'email'     => 'required|email|max:255|unique:users,email' . ($this->isEdit ? ',' . $this->userId : ''),
            'phone'     => 'nullable|string|max:30',
            'user_type' => 'required|string',
        ];

        if (! $this->isEdit || $this->password !== '') {
            $rules['password']              = 'required|string|min:8|confirmed';
            $rules['password_confirmation'] = 'required|string';
        }

        $validated = $this->validate($rules);

        if ($this->isEdit) {
            $user = User::findOrFail($this->userId);
            $user->update(array_filter([
                'name'      => $this->name,
                'last_name' => $this->last_name,
                'email'     => $this->email,
                'phone'     => $this->phone,
                'user_type' => $this->user_type,
                'is_active' => $this->is_active,
                'password'  => $this->password !== '' ? Hash::make($this->password) : null,
            ], fn ($v) => $v !== null));

            session()->flash('success', 'User updated successfully.');
        } else {
            $fullName = trim($this->name . ' ' . ($this->last_name ?? ''));

            User::create([
                'name'      => $this->name,
                'last_name' => $this->last_name,
                'email'     => $this->email,
                'phone'     => $this->phone,
                'user_type' => $this->user_type,
                'is_active' => $this->is_active,
                'username'  => generateUserName($fullName),
                'password'  => Hash::make($this->password),
            ]);

            session()->flash('success', 'User created successfully.');
        }

        $this->redirect(route('admin::users.list'), navigate: true);
    }
}; ?>

<div>
    {{-- Page Header --}}
    <div class="mb-6 flex items-center gap-4">
        <flux:button icon="arrow-left" variant="ghost" :href="route('admin::users.list')" wire:navigate />
        <div>
            <flux:heading size="xl">
                {{ $isEdit ? __('Edit User') : __('Add User') }}
            </flux:heading>
            <flux:subheading>
                {{ $isEdit ? __('Update user details below.') : __('Fill in the details to create a new user.') }}
            </flux:subheading>
        </div>
    </div>

    {{-- Flash Messages --}}
    @if (session('success'))
        <flux:callout variant="success" icon="check-circle" class="mb-4">{{ session('success') }}</flux:callout>
    @endif

    <form wire:submit="save" class="max-w-2xl">
        <div class="rounded-xl border border-neutral-200 bg-white dark:border-neutral-700 dark:bg-neutral-900">

            {{-- Section: Personal Info --}}
            <div class="border-b border-neutral-200 px-6 py-4 dark:border-neutral-700">
                <p class="text-sm font-semibold text-neutral-700 dark:text-neutral-300">{{ __('Personal Information') }}</p>
            </div>
            <div class="grid grid-cols-1 gap-5 px-6 py-6 sm:grid-cols-2">
                <flux:input
                    wire:model="name"
                    :label="__('First Name')"
                    type="text"
                    required
                    autofocus
                    :placeholder="__('First name')"
                />
                <flux:input
                    wire:model="last_name"
                    :label="__('Last Name')"
                    type="text"
                    :placeholder="__('Last name')"
                />
                <flux:input
                    wire:model="email"
                    :label="__('Email Address')"
                    type="email"
                    required
                    :placeholder="__('email@example.com')"
                    class="sm:col-span-2"
                />
                <flux:input
                    wire:model="phone"
                    :label="__('Phone Number')"
                    type="tel"
                    :placeholder="__('e.g. +1 234 567 890')"
                />
            </div>

            {{-- Section: Account --}}
            <div class="border-y border-neutral-200 px-6 py-4 dark:border-neutral-700">
                <p class="text-sm font-semibold text-neutral-700 dark:text-neutral-300">{{ __('Account') }}</p>
            </div>
            <div class="grid grid-cols-1 gap-5 px-6 py-6 sm:grid-cols-2">
                <div class="flex flex-col gap-2">
                    <flux:label>{{ __('Role') }}</flux:label>
                    <flux:select wire:model="user_type">
                        <flux:select.option value="" disabled>{{ __('Select role…') }}</flux:select.option>
                        @foreach (array_merge(App\Models\User::ADMIN_ROLES, App\Models\User::USER_ROLES) as $key => $label)
                            <flux:select.option value="{{ $key }}">{{ $label }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="user_type" />
                </div>
                <div class="flex items-end pb-1">
                    <flux:checkbox wire:model="is_active" :label="__('Active Account')" />
                </div>

                <flux:input
                    wire:model="password"
                    :label="$isEdit ? __('New Password') : __('Password')"
                    type="password"
                    viewable
                    :required="! $isEdit"
                    :placeholder="$isEdit ? __('Leave blank to keep current') : __('Min 8 characters')"
                />
                <flux:input
                    wire:model="password_confirmation"
                    :label="__('Confirm Password')"
                    type="password"
                    viewable
                    :required="! $isEdit"
                    :placeholder="__('Repeat password')"
                />
            </div>

            {{-- Actions --}}
            <div class="flex items-center justify-end gap-3 rounded-b-xl border-t border-neutral-200 bg-neutral-50 px-6 py-4 dark:border-neutral-700 dark:bg-neutral-800/50">
                <flux:button variant="ghost" :href="route('admin::users.list')" wire:navigate>
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button variant="primary" type="submit">
                    {{ $isEdit ? __('Save Changes') : __('Create User') }}
                </flux:button>
            </div>
        </div>
    </form>
</div>
