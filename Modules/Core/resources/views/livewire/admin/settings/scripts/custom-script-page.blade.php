<?php

use App\Facades\Setting;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('core::layouts.app')] class extends Component {
    public string $custom_css = '';

    public string $custom_js = '';

    public function mount(): void
    {
        $this->custom_css = (string) setting('custom_css', '', 'scripts_custom');
        $this->custom_js = (string) setting('custom_js', '', 'scripts_custom');
    }

    public function save(): void
    {
        $this->validate([
            'custom_css' => 'nullable|string',
            'custom_js' => 'nullable|string',
        ]);

        Setting::setMany([
            'custom_css' => $this->custom_css,
            'custom_js' => $this->custom_js,
        ], 'scripts_custom');

        session()->flash('success', __('Custom scripts saved.'));
    }
}; ?>

<div>
    <div class="mb-6">
        <flux:heading size="xl">{{ __('Custom Scripts') }}</flux:heading>
        <flux:subheading>{{ __('Site-wide custom CSS and JavaScript.') }}</flux:subheading>
    </div>

    @if (session('success'))
        <flux:callout variant="success" icon="check-circle" class="mb-4">{{ session('success') }}</flux:callout>
    @endif

    <form wire:submit="save" class="max-w-4xl space-y-6">
        <div class="admin-card overflow-hidden p-6">
            <x-core::forms.codemirror wire:model="custom_css" mode="css" :label="__('Custom CSS')" />
        </div>
        <div class="admin-card overflow-hidden p-6">
            <x-core::forms.codemirror wire:model="custom_js" mode="javascript" :label="__('Custom JavaScript')" />
            <div class="mt-6 flex justify-end">
                <flux:button variant="primary" type="submit">{{ __('Save Scripts') }}</flux:button>
            </div>
        </div>
    </form>
</div>
