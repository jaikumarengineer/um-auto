<?php

use App\Facades\Setting;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('core::layouts.app')] class extends Component {
    public string $head_scripts = '';

    public string $body_scripts = '';

    public function mount(): void
    {
        $this->head_scripts = (string) setting('head_scripts', '', 'scripts_third_party');
        $this->body_scripts = (string) setting('body_scripts', '', 'scripts_third_party');
    }

    public function save(): void
    {
        $this->validate([
            'head_scripts' => 'nullable|string',
            'body_scripts' => 'nullable|string',
        ]);

        Setting::setMany([
            'head_scripts' => $this->head_scripts,
            'body_scripts' => $this->body_scripts,
        ], 'scripts_third_party');

        $this->dispatch('toast', message: __('Third-party scripts saved.'), type: 'success');
    }
}; ?>

<div class="space-y-6">
    <div>
        <flux:heading size="xl">{{ __('Third-party Scripts') }}</flux:heading>
        <flux:subheading>{{ __('Inject analytics and widgets into head/body.') }}</flux:subheading>
    </div>

    <form wire:submit="save" class="w-full space-y-6">
        <div class="admin-card overflow-hidden p-6">
            <x-core::forms.codemirror wire:model="head_scripts" mode="html" :label="__('Head scripts')" :description="__('Rendered before </head>.')" />
        </div>
        <div class="admin-card overflow-hidden p-6">
            <x-core::forms.codemirror wire:model="body_scripts" mode="html" :label="__('Body scripts')" :description="__('Rendered before </body>.')" />
            <div class="mt-6 flex justify-end">
                <flux:button variant="primary" type="submit">{{ __('Save Scripts') }}</flux:button>
            </div>
        </div>
    </form>
</div>
