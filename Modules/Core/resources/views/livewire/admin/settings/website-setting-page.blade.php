<?php

use App\Facades\Setting;
use Livewire\Attributes\Layout;
use Livewire\WithFileUploads;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\Volt\Component;

new #[Layout('core::layouts.app')] class extends Component {
    use WithFileUploads;

    public string $site_title = '';

    public string $site_tagline = '';

    public string $meta_description = '';

    public ?string $logo_path = null;

    public ?string $favicon_path = null;

    public $logo = null;

    public $favicon = null;

    public function mount(): void
    {
        $this->site_title = (string) setting('site_title', config('app.name'), 'website');
        $this->site_tagline = (string) setting('site_tagline', '', 'website');
        $this->meta_description = (string) setting('meta_description', '', 'website');
        $this->logo_path = setting('logo_path', null, 'website');
        $this->favicon_path = setting('favicon_path', null, 'website');
    }

    public function save(): void
    {
        $this->validate([
            'site_title' => 'required|string|max:255',
            'site_tagline' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'logo' => 'nullable|image|max:2048',
            'favicon' => 'nullable|image|max:1024',
        ]);

        if ($this->logo instanceof TemporaryUploadedFile) {
            $this->logo_path = $this->logo->store('settings', 'public');
        }

        if ($this->favicon instanceof TemporaryUploadedFile) {
            $this->favicon_path = $this->favicon->store('settings', 'public');
        }

        Setting::setMany([
            'site_title' => $this->site_title,
            'site_tagline' => $this->site_tagline,
            'meta_description' => $this->meta_description,
            'logo_path' => $this->logo_path,
            'favicon_path' => $this->favicon_path,
        ], 'website');

        $this->dispatch('toast', message: __('Website settings saved.'), type: 'success');
    }
}; ?>

<div class="space-y-6">
    <div>
        <flux:heading size="xl">{{ __('Website Settings') }}</flux:heading>
        <flux:subheading>{{ __('Branding, titles, and public meta.') }}</flux:subheading>
    </div>

    <form wire:submit="save" class="w-full space-y-6">
        <div class="admin-card overflow-hidden">
            <div class="border-b border-zinc-200 px-6 py-4 dark:border-zinc-700">
                <p class="text-sm font-semibold">{{ __('Identity') }}</p>
            </div>
            <div class="grid gap-5 px-6 py-6 md:grid-cols-2">
                <div>
                    <flux:input wire:model="site_title" :label="__('Site title')" required />
                </div>
                <div>
                    <flux:input wire:model="site_tagline" :label="__('Tagline')" />
                </div>
                <div class="md:col-span-2">
                    <flux:textarea wire:model="meta_description" :label="__('Meta description')" rows="3" />
                </div>
            </div>
        </div>

        <div class="admin-card overflow-hidden">
            <div class="border-b border-zinc-200 px-6 py-4 dark:border-zinc-700">
                <p class="text-sm font-semibold">{{ __('Assets') }}</p>
            </div>
            <div class="grid gap-5 px-6 py-6 sm:grid-cols-2">
                <div>
                    <x-core::forms.filepond
                        wire:model="logo"
                        model="logo"
                        :label="__('Logo')"
                        :preview="$logo_path ? asset('storage/'.$logo_path) : null"
                        max-height="6rem"
                    />
                </div>
                <div>
                    <x-core::forms.filepond
                        wire:model="favicon"
                        model="favicon"
                        :label="__('Favicon')"
                        :accepted-file-types="['image/png', 'image/x-icon', 'image/svg+xml', 'image/jpeg']"
                        :preview="$favicon_path ? asset('storage/'.$favicon_path) : null"
                        shape="square"
                        max-height="4rem"
                    />
                </div>
            </div>
            <div class="flex justify-end border-t border-zinc-200 bg-[var(--admin-surface-muted)] px-6 py-4 dark:border-zinc-700">
                <flux:button variant="primary" type="submit">{{ __('Save Settings') }}</flux:button>
            </div>
        </div>
    </form>
</div>
