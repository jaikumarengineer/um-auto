<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Livewire\Volt\Component;

new #[Layout('core::layouts.app')] class extends Component {
    use WithFileUploads;

    public string $selectedTab = 'profile';

    public string $name = '';

    public string $last_name = '';

    public string $email = '';

    public string $phone = '';

    public string $country = '';

    public string $state = '';

    public string $city = '';

    public string $zip_code = '';

    public string $address = '';

    public ?string $image_path = null;

    public $avatar = null;

    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(): void
    {
        $this->fillFromUser(Auth::user());
    }

    public function saveProfile(): void
    {
        /** @var User $user */
        $user = Auth::user();

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:40'],
            'country' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'zip_code' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:5000'],
        ]);

        $user->fill([
            'name' => $validated['name'],
            'last_name' => $validated['last_name'] ?? null,
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'country' => $validated['country'] ?? null,
            'state' => $validated['state'] ?? null,
            'city' => $validated['city'] ?? null,
            'zip_code' => $validated['zip_code'] ?? null,
            'address_line_1' => $validated['address'] ?? null,
        ]);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        $this->fillFromUser($user->fresh());
        $this->dispatch('toast', message: __('Profile saved successfully.'), type: 'success');
    }

    public function saveAvatar(): void
    {
        /** @var User $user */
        $user = Auth::user();

        $this->validate([
            'avatar' => ['required', 'image', 'max:2048'],
        ]);

        if ($this->avatar instanceof TemporaryUploadedFile) {
            $path = $this->avatar->store('avatars', 'public');
            $user->image = 'storage/'.$path;
            $user->save();
            $this->image_path = $user->image;
            $this->avatar = null;
        }

        $this->dispatch('toast', message: __('Avatar updated successfully.'), type: 'success');
        $this->dispatch('modal-close', name: 'profile-avatar');
    }

    public function changePassword(): void
    {
        try {
            $validated = $this->validate([
                'current_password' => ['required', 'string', 'current_password'],
                'password' => ['required', 'string', Password::defaults(), 'confirmed'],
            ]);
        } catch (ValidationException $e) {
            $this->reset('current_password', 'password', 'password_confirmation');

            throw $e;
        }

        Auth::user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        $this->reset('current_password', 'password', 'password_confirmation');
        $this->dispatch('toast', message: __('Password changed successfully.'), type: 'success');
    }

    protected function fillFromUser(User $user): void
    {
        $this->name = (string) $user->name;
        $this->last_name = (string) ($user->last_name ?? '');
        $this->email = (string) $user->email;
        $this->phone = (string) ($user->phone ?? '');
        $this->country = (string) ($user->country ?? '');
        $this->state = (string) ($user->state ?? '');
        $this->city = (string) ($user->city ?? '');
        $this->zip_code = (string) ($user->zip_code ?? '');
        $this->address = (string) ($user->address_line_1 ?? '');
        $this->image_path = $user->image;
    }
}; ?>

@php
    $user = auth()->user();
    $roleLabels = array_merge(User::ADMIN_ROLES, User::USER_ROLES);
    $roleLabel = $roleLabels[$user->user_type ?? ''] ?? ucfirst((string) ($user->user_type ?? 'User'));

    $avatarSrc = $image_path
        ? (str_starts_with($image_path, 'http') || str_starts_with($image_path, 'data:')
            ? $image_path
            : asset($image_path))
        : $user->avatar_url;

    $displayName = trim($name.' '.$last_name) ?: $user->name;
@endphp

<div class="w-full space-y-6">
    <div>
        <flux:heading size="xl">{{ __('Profile') }}</flux:heading>
        <flux:subheading>{{ __('Here you can edit your profile') }}</flux:subheading>
    </div>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-6">
        {{-- Left: identity card --}}
        <div class="lg:col-span-2">
            <div class="admin-card relative overflow-hidden p-6">
                <div class="absolute end-4 top-4 hidden lg:block">
                    <span class="inline-flex rounded-full bg-[var(--admin-primary)] px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wide text-white">
                        {{ $roleLabel }}
                    </span>
                </div>

                <div class="flex flex-col items-center gap-4 text-center lg:items-start lg:text-start">
                    <img
                        src="{{ $avatarSrc }}"
                        alt=""
                        class="size-24 rounded-full object-cover shadow-sm ring-4 ring-white dark:ring-zinc-800"
                    />

                    <div class="min-w-0 space-y-1">
                        <h2 class="truncate text-2xl font-semibold tracking-tight text-zinc-900 dark:text-white">
                            {{ $displayName }}
                        </h2>
                        <div class="flex flex-col gap-1.5 text-sm text-zinc-500 dark:text-zinc-400">
                            <div class="flex items-center justify-center gap-2 lg:justify-start">
                                <flux:icon.envelope variant="outline" class="size-4 shrink-0 stroke-[1.5]" />
                                <span class="truncate">{{ $email ?: '—' }}</span>
                            </div>
                            <div class="flex items-center justify-center gap-2 lg:justify-start">
                                <flux:icon.phone variant="outline" class="size-4 shrink-0 stroke-[1.5]" />
                                <span class="truncate">{{ $phone ?: '—' }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-6 text-center">
                    <flux:modal.trigger name="profile-avatar">
                        <flux:button type="button" variant="filled" size="sm">
                            {{ __('Change Photo') }}
                        </flux:button>
                    </flux:modal.trigger>
                </div>
            </div>
        </div>

        {{-- Right: tabs --}}
        <div class="lg:col-span-4">
            <div class="admin-card overflow-hidden">
                <div class="flex gap-1 overflow-x-auto border-b border-zinc-200 bg-[var(--admin-surface-muted)] px-3 py-2 dark:border-zinc-700">
                    <button
                        type="button"
                        wire:click="$set('selectedTab', 'profile')"
                        @class([
                            'inline-flex shrink-0 items-center gap-2 rounded-lg px-3.5 py-2 text-sm font-medium transition whitespace-nowrap',
                            'bg-white text-zinc-900 shadow-sm dark:bg-zinc-800 dark:text-white' => $selectedTab === 'profile',
                            'text-zinc-600 hover:bg-white/70 dark:text-zinc-400 dark:hover:bg-zinc-800/70' => $selectedTab !== 'profile',
                        ])
                    >
                        <flux:icon.user variant="outline" class="size-4 stroke-[1.5]" />
                        {{ __('Profile') }}
                    </button>
                    <button
                        type="button"
                        wire:click="$set('selectedTab', 'security')"
                        @class([
                            'inline-flex shrink-0 items-center gap-2 rounded-lg px-3.5 py-2 text-sm font-medium transition whitespace-nowrap',
                            'bg-white text-zinc-900 shadow-sm dark:bg-zinc-800 dark:text-white' => $selectedTab === 'security',
                            'text-zinc-600 hover:bg-white/70 dark:text-zinc-400 dark:hover:bg-zinc-800/70' => $selectedTab !== 'security',
                        ])
                    >
                        <flux:icon.lock-closed variant="outline" class="size-4 stroke-[1.5]" />
                        {{ __('Change Password') }}
                    </button>
                </div>

                <div class="p-5 sm:p-6">
                    @if ($selectedTab === 'profile')
                        <form wire:submit="saveProfile" class="space-y-5">
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <flux:input wire:model="name" :label="__('First Name')" required autocomplete="given-name" />
                                <flux:input wire:model="last_name" :label="__('Last Name')" autocomplete="family-name" />
                                <flux:input wire:model="email" type="email" :label="__('Email')" required autocomplete="email" />
                                <x-core::forms.phone wire:model="phone" :label="__('Phone')" />
                                <flux:input wire:model="country" :label="__('Country')" />
                                <flux:input wire:model="state" :label="__('State')" />
                                <flux:input wire:model="city" :label="__('City')" />
                                <flux:input wire:model="zip_code" :label="__('Zipcode')" />
                                <div class="md:col-span-2">
                                    <flux:textarea wire:model="address" :label="__('Address')" rows="3" />
                                </div>
                            </div>

                            <div class="flex justify-center pt-2">
                                <flux:button variant="primary" type="submit" wire:loading.attr="disabled">
                                    {{ __('Save Profile') }}
                                </flux:button>
                            </div>
                        </form>
                    @else
                        <form wire:submit="changePassword" class="mx-auto max-w-lg space-y-4">
                            <flux:input
                                wire:model="current_password"
                                :label="__('Old Password')"
                                type="password"
                                required
                                viewable
                                autocomplete="current-password"
                            />
                            <flux:input
                                wire:model="password"
                                :label="__('New Password')"
                                type="password"
                                required
                                viewable
                                autocomplete="new-password"
                            />
                            <flux:input
                                wire:model="password_confirmation"
                                :label="__('Retype Password')"
                                type="password"
                                required
                                viewable
                                autocomplete="new-password"
                            />

                            <div class="flex justify-center pt-2">
                                <flux:button variant="primary" type="submit" wire:loading.attr="disabled">
                                    {{ __('Change Password') }}
                                </flux:button>
                            </div>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Change photo modal --}}
    <flux:modal name="profile-avatar" class="max-w-lg">
        <div class="space-y-5">
            <div>
                <flux:heading size="lg">{{ __('Change Photo') }}</flux:heading>
                <flux:subheading>{{ __('Recommended dimension: 500 × 500 pixels') }}</flux:subheading>
            </div>

            <div class="flex justify-center">
                <img
                    src="{{ $avatarSrc }}"
                    alt=""
                    class="size-28 rounded-full object-cover ring-4 ring-zinc-100 dark:ring-zinc-800"
                />
            </div>

            <x-core::forms.filepond
                wire:model="avatar"
                model="avatar"
                :label="__('Avatar Image')"
                :accepted-file-types="['image/png', 'image/jpeg', 'image/webp']"
            />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button type="button" variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="button" variant="primary" wire:click="saveAvatar" wire:loading.attr="disabled">
                    {{ __('Save') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
