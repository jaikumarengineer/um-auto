<?php

use Livewire\Attributes\Layout;
use Livewire\WithFileUploads;
use Livewire\Volt\Component;

new #[Layout('core::layouts.app')] class extends Component {
    use WithFileUploads;

    public string $country = 'us';

    public array $roles = [];

    public array $months = ['1', '2'];

    public string $phone = '';

    public array $tags = ['admin', 'livewire'];

    public $demo_file = null;

    public string $bio = '<p>Hello from <strong>CKEditor 5</strong>.</p>';

    public string $bio4 = '<p>Hello from <strong>CKEditor 4</strong> with image upload.</p>';

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
    <div>
        <flux:heading size="xl">{{ __('Form components') }}</flux:heading>
        <flux:subheading>{{ __('Reusable Alpine-powered inputs styled for the admin shell.') }}</flux:subheading>
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
            <x-core::forms.filepond wire:model="demo_file" model="demo_file" :label="__('Upload')" />
            <x-core::forms.editor wire:model="bio" :label="__('Bio (CKEditor 5)')" />
            <x-core::forms.ckeditor4 wire:model="bio4" :label="__('Bio (CKEditor 4 + image upload)')" :height="280" />
            <x-core::forms.codemirror wire:model="snippet" mode="javascript" :label="__('Snippet')" />
            <div class="flex justify-end">
                <flux:button type="submit" variant="primary">{{ __('Save demo state') }}</flux:button>
            </div>
        </div>
    </form>
</div>
