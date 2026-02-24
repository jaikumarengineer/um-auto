<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;

new #[Layout('core::layouts.app')] class extends Component {

    public string $app_name    = '';
    public string $app_url     = '';
    public string $app_email   = '';
    public string $timezone    = '';
    public string $locale      = '';

    public function mount(): void
    {
        $this->app_name  = config('app.name', '');
        $this->app_url   = config('app.url', '');
        $this->app_email = config('mail.from.address', '');
        $this->timezone  = config('app.timezone', 'UTC');
        $this->locale    = config('app.locale', 'en');
    }

    public function save(): void
    {
        $this->validate([
            'app_name'  => 'required|string|max:255',
            'app_url'   => 'required|url|max:255',
            'app_email' => 'required|email|max:255',
            'timezone'  => 'required|string',
            'locale'    => 'required|string',
        ]);

        // Persist to .env or a settings table in a real project
        session()->flash('success', 'Settings saved successfully.');
    }
}; ?>

<div>
    {{-- Page Header --}}
    <div class="mb-6">
        <flux:heading size="xl">{{ __('General Settings') }}</flux:heading>
        <flux:subheading>{{ __('Configure basic application settings.') }}</flux:subheading>
    </div>

    @if (session('success'))
        <flux:callout variant="success" icon="check-circle" class="mb-4">{{ session('success') }}</flux:callout>
    @endif

    <form wire:submit="save" class="max-w-2xl">
        <div class="rounded-xl border border-neutral-200 bg-white dark:border-neutral-700 dark:bg-neutral-900">

            {{-- Application --}}
            <div class="border-b border-neutral-200 px-6 py-4 dark:border-neutral-700">
                <p class="text-sm font-semibold text-neutral-700 dark:text-neutral-300">{{ __('Application') }}</p>
            </div>
            <div class="grid grid-cols-1 gap-5 px-6 py-6 sm:grid-cols-2">
                <flux:input
                    wire:model="app_name"
                    :label="__('Application Name')"
                    required
                    placeholder="My App"
                    class="sm:col-span-2"
                />
                <flux:input
                    wire:model="app_url"
                    :label="__('Application URL')"
                    type="url"
                    required
                    placeholder="https://example.com"
                    class="sm:col-span-2"
                />
                <flux:input
                    wire:model="app_email"
                    :label="__('From Email Address')"
                    type="email"
                    required
                    placeholder="no-reply@example.com"
                    class="sm:col-span-2"
                />
            </div>

            {{-- Localisation --}}
            <div class="border-y border-neutral-200 px-6 py-4 dark:border-neutral-700">
                <p class="text-sm font-semibold text-neutral-700 dark:text-neutral-300">{{ __('Localisation') }}</p>
            </div>
            <div class="grid grid-cols-1 gap-5 px-6 py-6 sm:grid-cols-2">
                <div class="flex flex-col gap-2">
                    <flux:label>{{ __('Timezone') }}</flux:label>
                    <flux:select wire:model="timezone">
                        @foreach (timezone_identifiers_list() as $tz)
                            <flux:select.option value="{{ $tz }}">{{ $tz }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="timezone" />
                </div>

                <div class="flex flex-col gap-2">
                    <flux:label>{{ __('Locale') }}</flux:label>
                    <flux:select wire:model="locale">
                        <flux:select.option value="en">English</flux:select.option>
                        <flux:select.option value="fr">French</flux:select.option>
                        <flux:select.option value="de">German</flux:select.option>
                        <flux:select.option value="es">Spanish</flux:select.option>
                        <flux:select.option value="ar">Arabic</flux:select.option>
                    </flux:select>
                    <flux:error name="locale" />
                </div>
            </div>

            {{-- Actions --}}
            <div class="flex items-center justify-end gap-3 rounded-b-xl border-t border-neutral-200 bg-neutral-50 px-6 py-4 dark:border-neutral-700 dark:bg-neutral-800/50">
                <flux:button variant="primary" type="submit">
                    {{ __('Save Settings') }}
                </flux:button>
            </div>
        </div>
    </form>
</div>
