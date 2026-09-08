<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Livewire\Volt\Component;

new #[Layout('core::layouts.app')] class extends Component {
    use WithFileUploads;

    public ?int $userId = null;

    public bool $isEdit = false;

    public string $name = '';

    public string $last_name = '';

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

    public function mount(?int $user_id = null): void
    {
        if ($user_id) {
            $user = User::findOrFail($user_id);
            $this->isEdit = true;
            $this->userId = $user->id;
            $this->name = (string) $user->name;
            $this->last_name = (string) ($user->last_name ?? '');
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
        }
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
            'phone' => ['nullable', 'string', 'max:40'],
            'country' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'zip_code' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:5000'],
            'user_type' => ['required', 'string', Rule::in(array_keys(array_merge(User::ADMIN_ROLES, User::USER_ROLES)))],
            'is_active' => ['boolean'],
            'image' => ['nullable', 'image', 'max:2048'],
        ];

        if (! $this->isEdit || $this->password !== '') {
            $rules['password'] = ['required', 'string', Password::defaults(), 'confirmed'];
        }

        $this->validate($rules);

        $payload = [
            'name' => $this->name,
            'last_name' => $this->last_name ?: null,
            'email' => $this->email,
            'phone' => $this->phone ?: null,
            'country' => $this->country ?: null,
            'state' => $this->state ?: null,
            'city' => $this->city ?: null,
            'zip_code' => $this->zip_code ?: null,
            'address_line_1' => $this->address ?: null,
            'user_type' => $this->user_type,
            'is_active' => $this->is_active,
        ];

        if ($this->image instanceof TemporaryUploadedFile) {
            $path = $this->image->store('avatars', 'public');
            $payload['image'] = 'storage/'.$path;
        }

        if ($this->password !== '') {
            $payload['password'] = Hash::make($this->password);
        }

        if ($this->isEdit) {
            User::findOrFail($this->userId)->update($payload);
            $message = __('User updated successfully.');
        } else {
            $fullName = trim($this->name.' '.($this->last_name ?? ''));
            $payload['username'] = generateUserName($fullName);
            $payload['password'] = $payload['password'] ?? Hash::make($this->password);
            User::create($payload);
            $message = __('User created successfully.');
        }

        $this->dispatch('toast', message: $message, type: 'success');
        $this->redirect(route('admin::users.list'), navigate: true);
    }
}; ?>

@php
    $roleOptions = array_merge(User::ADMIN_ROLES, User::USER_ROLES);

    $avatarSrc = $image_path
        ? (str_starts_with($image_path, 'http') || str_starts_with($image_path, 'data:')
            ? $image_path
            : asset($image_path))
        : null;
@endphp

<div class="w-full space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-start gap-3">
            <flux:button
                icon="arrow-left"
                variant="ghost"
                size="sm"
                class="mt-1"
                :href="route('admin::users.list')"
                wire:navigate
                :aria-label="__('Back to list')"
            />
            <div>
                <flux:heading size="xl">
                    {{ $isEdit ? __('Edit Website User') : __('New Website User') }}
                </flux:heading>
                <flux:subheading>
                    {{ __('Here you can add or edit the details of website user.') }}
                </flux:subheading>
            </div>
        </div>
    </div>

    <form wire:submit="save" class="w-full">
        <div class="admin-card overflow-hidden">
            <div class="grid grid-cols-1 gap-5 px-6 py-6 lg:grid-cols-2">
                {{-- Avatar --}}
                <div class="lg:col-span-2">
                    <div class="flex flex-col items-start gap-4 lg:flex-row lg:items-center">
                        <div class="w-full lg:w-2/5">
                            <x-core::forms.filepond
                                wire:model="image"
                                model="image"
                                :label="__('Avatar Image')"
                                :description="__('Dimension: 500 × 500 pixels')"
                                :accepted-file-types="['image/png', 'image/jpeg', 'image/webp']"
                            />
                        </div>
                        <div class="flex items-center gap-3">
                            @if ($avatarSrc)
                                <img
                                    src="{{ $avatarSrc }}"
                                    alt=""
                                    class="size-16 rounded-full object-cover shadow-sm ring-2 ring-zinc-100 dark:ring-zinc-800"
                                />
                            @else
                                <div class="flex size-16 items-center justify-center rounded-full bg-[color-mix(in_srgb,var(--admin-primary)_15%,white)] text-sm font-semibold text-[var(--admin-primary)] shadow-sm">
                                    {{ strtoupper(substr($name ?: 'U', 0, 1)) }}{{ strtoupper(substr($last_name ?: '', 0, 1)) }}
                                </div>
                            @endif
                            <p class="text-xs text-zinc-500">{{ __('Current preview') }}</p>
                        </div>
                    </div>
                </div>

                <flux:input wire:model="name" :label="__('First Name')" required autofocus autocomplete="given-name" />
                <flux:input wire:model="last_name" :label="__('Last Name')" autocomplete="family-name" />
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

                <div>
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
</div>
