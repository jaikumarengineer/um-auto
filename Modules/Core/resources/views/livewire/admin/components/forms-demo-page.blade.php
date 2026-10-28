<?php

use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('core::layouts.app')] class extends Component {

    public string $country = 'us';

    public array $roles = [];

    public array $months = ['1', '2'];

    public string $phone = '';

    public array $tags = ['admin', 'livewire'];

    public $demo_file = null;

    public string $bio = '<p>Hello from <strong>CKEditor 5</strong>.</p>';

    public string $bio4 = '<p>Hello from <strong>CKEditor 4</strong> with image upload.</p>';

    public string $bio_tiptap = '<p>Hello from <strong>Tiptap</strong> — try inserting an image or a table.</p>';

    public string $starts_at = '';

    public string $snippet = "console.log('Starter Kit');\n";

    public function save(): void
    {
        $this->dispatch('toast', message: __('Demo form values captured in Livewire state.'), type: 'success');
    }
}; ?>

@php
    $countryOptions = [
        'us' => 'United States',
        'gb' => 'United Kingdom',
        'in' => 'India',
        'ae' => 'United Arab Emirates',
        'ca' => 'Canada',
    ];
    $roleOptions = [
        'admin' => 'Admin',
        'editor' => 'Editor',
        'viewer' => 'Viewer',
    ];
    $monthOptions = [
        '1' => 'January',
        '2' => 'February',
        '3' => 'March',
        '4' => 'April',
        '5' => 'May',
        '6' => 'June',
        '7' => 'July',
        '8' => 'August',
        '9' => 'September',
        '10' => 'October',
        '11' => 'November',
        '12' => 'December',
    ];
@endphp

<div class="space-y-6">
    <x-core::ui.page-header
        :title="__('Form components')"
        :subtitle="__('Reusable Alpine-powered inputs styled for the admin shell.')"
        :breadcrumbs="[
            ['label' => __('Dashboard'), 'route' => 'admin::dashboard'],
            ['label' => __('Components')],
            ['label' => __('Forms')],
        ]"
    />

    {{-- Tab UI variants — pick whichever style fits a given page --}}
    <div class="admin-card space-y-8 p-6">
        <div>
            <flux:heading size="lg">{{ __('Tab style variants') }}</flux:heading>
            <flux:subheading>{{ __('Extra tab UI patterns you can reuse besides the pill tabs used on Settings pages.') }}</flux:subheading>
        </div>

        @php
            $tabDemoItems = [
                'overview' => ['label' => __('Overview'), 'icon' => 'squares-2x2'],
                'members' => ['label' => __('Members'), 'icon' => 'users'],
                'billing' => ['label' => __('Billing'), 'icon' => 'credit-card'],
                'security' => ['label' => __('Security'), 'icon' => 'shield-check'],
            ];
        @endphp

        {{-- 1) Underline tabs --}}
        <div x-data="{ tab: 'overview' }" class="space-y-4">
            <p class="text-xs font-semibold uppercase tracking-wider text-zinc-400">{{ __('1. Underline') }}</p>
            <div class="admin-tabs-underline">
                @foreach ($tabDemoItems as $key => $item)
                    <button
                        type="button"
                        class="admin-tabs-underline-btn"
                        x-on:click="tab = @js($key)"
                        x-bind:class="{ 'is-active': tab === @js($key) }"
                    >
                        {{ $item['label'] }}
                    </button>
                @endforeach
            </div>
            <div class="text-sm text-zinc-500 dark:text-zinc-400">
                @foreach ($tabDemoItems as $key => $item)
                    <p x-show="tab === @js($key)" x-cloak>
                        {{ __(':label panel content goes here.', ['label' => $item['label']]) }}
                    </p>
                @endforeach
            </div>
        </div>

        {{-- 2) Boxed / segmented control --}}
        <div x-data="{ tab: 'overview' }" class="space-y-4">
            <p class="text-xs font-semibold uppercase tracking-wider text-zinc-400">{{ __('2. Boxed / segmented') }}</p>
            <div class="admin-tabs-boxed">
                @foreach ($tabDemoItems as $key => $item)
                    <button
                        type="button"
                        class="admin-tabs-boxed-btn"
                        x-on:click="tab = @js($key)"
                        x-bind:class="{ 'is-active': tab === @js($key) }"
                    >
                        {{ $item['label'] }}
                    </button>
                @endforeach
            </div>
            <div class="text-sm text-zinc-500 dark:text-zinc-400">
                @foreach ($tabDemoItems as $key => $item)
                    <p x-show="tab === @js($key)" x-cloak>
                        {{ __(':label panel content goes here.', ['label' => $item['label']]) }}
                    </p>
                @endforeach
            </div>
        </div>

        {{-- 3) Vertical tabs (settings-rail style) --}}
        <div x-data="{ tab: 'overview' }" class="space-y-4">
            <p class="text-xs font-semibold uppercase tracking-wider text-zinc-400">{{ __('3. Vertical rail') }}</p>
            <div class="grid gap-4 sm:grid-cols-4">
                <nav class="admin-tabs-vertical-nav sm:col-span-1">
                    @foreach ($tabDemoItems as $key => $item)
                        <button
                            type="button"
                            class="admin-tabs-vertical-btn"
                            x-on:click="tab = @js($key)"
                            x-bind:class="{ 'is-active': tab === @js($key) }"
                        >
                            <flux:icon :icon="$item['icon']" variant="outline" class="size-4 shrink-0" />
                            {{ $item['label'] }}
                        </button>
                    @endforeach
                </nav>
                <div class="rounded-xl border border-zinc-200 p-4 text-sm text-zinc-500 sm:col-span-3 dark:border-zinc-700 dark:text-zinc-400">
                    @foreach ($tabDemoItems as $key => $item)
                        <p x-show="tab === @js($key)" x-cloak>
                            {{ __(':label panel content goes here.', ['label' => $item['label']]) }}
                        </p>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
    
    <form wire:submit="save" class="grid gap-6 xl:grid-cols-2">
        <div class="admin-card space-y-5 p-6">
            <x-core::forms.select2 wire:model="country" :label="__('Country')" :options="$countryOptions" />
            <x-core::forms.multiselect wire:model="months" :label="__('Months')" :options="$monthOptions" :placeholder="__('Select months…')" />
            <x-core::forms.select2 wire:model="roles" :label="__('Roles (Select2 multi)')" :options="$roleOptions" multiple />
            <x-core::forms.phone wire:model="phone" :label="__('Phone')" />
            <x-core::forms.tagify wire:model="tags" :label="__('Tags')" :whitelist="['admin', 'livewire', 'flux', 'vite']" />
            <x-core::forms.flatpickr wire:model="starts_at" mode="datetime" :label="__('Starts at')" />
        </div>

        <div class="admin-card space-y-5 p-6">
            <x-core::forms.filepond wire:model="demo_file" model="demo_file" folder="uploads" :label="__('Upload')" />
            <x-core::forms.editor wire:model="bio" :label="__('Bio (CKEditor 5)')" />
            <x-core::forms.ckeditor4 wire:model="bio4" :label="__('Bio (CKEditor 4 + image upload)')" :height="280" />
            <x-core::forms.tiptap wire:model="bio_tiptap" :label="__('Bio (Tiptap + image upload)')" :height="240" />
            <x-core::forms.codemirror wire:model="snippet" mode="javascript" :label="__('Snippet')" />
            <div class="flex justify-end">
                <flux:button type="submit" variant="primary">{{ __('Save demo state') }}</flux:button>
            </div>
        </div>
    </form>
</div>
