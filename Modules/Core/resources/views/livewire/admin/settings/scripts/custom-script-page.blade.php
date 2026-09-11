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

        $this->dispatch(
            'SweetMessage',
            type: 'success',
            title: __('Success'),
            message: __('Custom scripts saved.'),
        );
    }
}; ?>

<div class="space-y-6">
    <x-core::ui.page-header
        :title="__('Custom Scripts')"
        :subtitle="__('Site-wide custom CSS and JavaScript.')"
        :breadcrumbs="[
            ['label' => __('Dashboard'), 'route' => 'admin::dashboard'],
            ['label' => __('Settings')],
            ['label' => __('Custom Scripts')],
        ]"
    />

    <form wire:submit="save" class="w-full space-y-6">
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
