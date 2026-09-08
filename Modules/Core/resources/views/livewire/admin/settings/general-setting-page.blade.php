<?php

use App\Helpers\Admin\SettingHelper;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Livewire\Volt\Component;

new #[Layout('core::layouts.app')] class extends Component {
    use WithFileUploads;

    public string $selectedTab = 'main-tab';

    /** @var array<string, mixed> */
    public array $request = [];

    public function mount(): void
    {
        $this->loadRequest();
    }

    public function save(): void
    {
        $imageKeys = [
            'meta_os_image',
            'site_logo',
            'site_logo_2',
            'site_mobile_logo',
            'site_favicon',
            'footer_logo',
            'admin_logo',
        ];

        foreach ($imageKeys as $key) {
            $value = $this->request[$key] ?? null;
            if ($value instanceof TemporaryUploadedFile) {
                $this->request[$key] = $value->store('settings/logo', 'public');
            }
        }

        if (isset($this->request['meta_tags']) && is_array($this->request['meta_tags'])) {
            $this->request['meta_tags'] = array_values($this->request['meta_tags']);
        }

        $this->request['captcha'] = (int) ($this->request['captcha'] ?? 0);
        $this->request['maintenance_mode'] = (int) ($this->request['maintenance_mode'] ?? 0);

        SettingHelper::createOrUpdateSetting($this->request, 'general');

        if (! empty($this->request['site_name'])) {
            update_env([
                'APP_NAME' => Str::replace(' ', '', (string) $this->request['site_name']),
            ]);
        }

        if (! empty($this->request['app_base_url'])) {
            update_env([
                'APP_URL' => (string) $this->request['app_base_url'],
            ]);
        }

        session()->flash('success', __('General settings updated successfully.'));
        $this->loadRequest();
    }

    protected function loadRequest(): void
    {
        $keys = [
            'site_name',
            'site_description',
            'site_title',
            'meta_title',
            'meta_description',
            'meta_tags',
            'site_logo',
            'site_logo_2',
            'site_mobile_logo',
            'site_favicon',
            'admin_logo',
            'footer_logo',
            'meta_os_image',
            'company_name',
            'company_email',
            'company_phone',
            'company_about',
            'company_address',
            'fb_link',
            'twitter_link',
            'instagram_link',
            'pinterest_link',
            'linked_in_link',
            'youtube_link',
            'google_plus_link',
            'footer_description',
            'captcha',
            'editor_layout',
            'app_base_url',
            'maintenance_mode',
            'timezone',
            'locale',
            'app_email',
        ];

        $this->request = SettingHelper::getByKeys($keys, 'general', [
            'site_name' => config('app.name'),
            'site_title' => config('app.name'),
            'meta_tags' => [],
            'editor_layout' => 'ck-editor-4',
            'app_base_url' => config('app.url'),
            'captcha' => 1,
            'maintenance_mode' => 0,
            'timezone' => config('app.timezone', 'UTC'),
            'locale' => config('app.locale', 'en'),
            'app_email' => config('mail.from.address'),
            'site_logo' => null,
            'site_logo_2' => null,
            'site_mobile_logo' => null,
            'site_favicon' => null,
            'admin_logo' => null,
            'footer_logo' => null,
            'meta_os_image' => null,
        ]);

        $this->request['meta_tags'] = setting_json_decode($this->request['meta_tags'] ?? []);
        $this->request['captcha'] = (bool) ($this->request['captcha'] ?? false);
        $this->request['maintenance_mode'] = (bool) ($this->request['maintenance_mode'] ?? false);
    }
}; ?>

@php
    $tabs = [
        'main-tab' => __('Main'),
        'logo-tab' => __('Logo'),
        'company-tab' => __('Company'),
        'social-tab' => __('Social Links'),
        'footer-tab' => __('Footer'),
        'backend-tab' => __('Backend'),
        'other-tab' => __('Other'),
    ];

    $editorOptions = [
        'ck-editor-4' => 'CK Editor 4',
        'ck-editor-5' => 'CK Editor 5',
        'tiny-mce' => 'TinyMCE',
        'quill-editor' => 'Quill Rich Text Editor',
    ];

    $timezoneOptions = collect(timezone_identifiers_list())->mapWithKeys(fn ($tz) => [$tz => $tz])->all();
    $localeOptions = [
        'en' => 'English',
        'fr' => 'French',
        'de' => 'German',
        'es' => 'Spanish',
        'ar' => 'Arabic',
    ];
@endphp

<div class="space-y-6">
    <div>
        <flux:heading size="xl">{{ __('General Settings') }}</flux:heading>
        <flux:subheading>{{ __('Configure website, company, social, and backend options.') }}</flux:subheading>
    </div>

    @if (session('success'))
        <flux:callout variant="success" icon="check-circle">{{ session('success') }}</flux:callout>
    @endif

    <form wire:submit="save" class="admin-card admin-settings-tabs overflow-hidden">
        <div class="flex flex-wrap gap-1 border-b border-zinc-200 bg-[var(--admin-surface-muted)] px-3 py-2 dark:border-zinc-700">
            @foreach ($tabs as $tab => $label)
                <button
                    type="button"
                    wire:click="$set('selectedTab', '{{ $tab }}')"
                    @class([
                        'rounded-lg px-3 py-2 text-sm font-medium transition',
                        'bg-white text-zinc-900 shadow-sm dark:bg-zinc-800 dark:text-white' => $selectedTab === $tab,
                        'text-zinc-600 hover:bg-white/70 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800/70 dark:hover:text-white' => $selectedTab !== $tab,
                    ])
                >
                    {{ $label }}
                </button>
            @endforeach
        </div>

        <div class="space-y-5 px-6 py-6">
            <div class="admin-tab-panel" @if ($selectedTab !== 'main-tab') hidden @endif>
                <div class="grid gap-5 sm:grid-cols-2">
                    <flux:input wire:model="request.site_name" :label="__('Site Name')" class="sm:col-span-2" />
                    <flux:textarea wire:model="request.site_description" :label="__('Site Description')" rows="3" class="sm:col-span-2" />
                    <flux:input wire:model="request.site_title" :label="__('Site Title')" />
                    <flux:input wire:model="request.meta_title" :label="__('Meta Title')" :description="__('Max 110 characters')" />
                    <flux:textarea wire:model="request.meta_description" :label="__('Meta Description')" rows="3" class="sm:col-span-2" :description="__('Max 160 characters')" />
                    <div class="sm:col-span-2">
                        <x-core::forms.filepond wire:model="request.meta_os_image" model="request.meta_os_image" :label="__('Meta OS Image')" :description="__('Recommended 1200 × 630')" />
                        @if (! empty($request['meta_os_image']) && is_string($request['meta_os_image']))
                            <img src="{{ asset('storage/'.$request['meta_os_image']) }}" alt="" class="mt-3 max-h-24 rounded-lg border border-zinc-200 object-contain dark:border-zinc-700" />
                        @endif
                    </div>
                    <div class="sm:col-span-2">
                        <x-core::forms.tagify wire:model="request.meta_tags" :label="__('Meta Tags')" />
                    </div>
                </div>
            </div>

            <div class="admin-tab-panel" @if ($selectedTab !== 'logo-tab') hidden @endif>
                <div class="grid gap-6 sm:grid-cols-2">
                    @foreach ([
                        'site_logo' => __('Website Logo'),
                        'site_logo_2' => __('Website Logo 2'),
                        'site_mobile_logo' => __('Mobile Logo'),
                        'site_favicon' => __('Favicon'),
                    ] as $key => $label)
                        <div>
                            <x-core::forms.filepond wire:model="request.{{ $key }}" model="request.{{ $key }}" :label="$label" />
                            @if (! empty($request[$key]) && is_string($request[$key]))
                                <img src="{{ asset('storage/'.$request[$key]) }}" alt="" class="mt-3 max-h-16 rounded-lg border border-zinc-200 object-contain dark:border-zinc-700" />
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="admin-tab-panel" @if ($selectedTab !== 'company-tab') hidden @endif>
                <div class="grid gap-5 sm:grid-cols-2">
                    <flux:input wire:model="request.company_name" :label="__('Company Name')" />
                    <flux:input wire:model="request.company_email" type="email" :label="__('Company Email')" />
                    <div class="sm:col-span-2">
                        <x-core::forms.phone wire:model="request.company_phone" :label="__('Company Phone')" />
                    </div>
                    <flux:textarea wire:model="request.company_about" :label="__('About Company')" rows="3" class="sm:col-span-2" />
                    <flux:textarea wire:model="request.company_address" :label="__('Company Address')" rows="3" class="sm:col-span-2" />
                </div>
            </div>

            <div class="admin-tab-panel" @if ($selectedTab !== 'social-tab') hidden @endif>
                <div class="grid gap-5 sm:grid-cols-2">
                    <flux:input wire:model="request.fb_link" :label="__('Facebook')" />
                    <flux:input wire:model="request.twitter_link" :label="__('Twitter / X')" />
                    <flux:input wire:model="request.instagram_link" :label="__('Instagram')" />
                    <flux:input wire:model="request.pinterest_link" :label="__('Pinterest')" />
                    <flux:input wire:model="request.linked_in_link" :label="__('LinkedIn')" />
                    <flux:input wire:model="request.youtube_link" :label="__('YouTube')" />
                    <flux:input wire:model="request.google_plus_link" :label="__('Google Plus')" class="sm:col-span-2" />
                </div>
            </div>

            <div class="admin-tab-panel" @if ($selectedTab !== 'footer-tab') hidden @endif>
                <div class="grid gap-5">
                    <div>
                        <x-core::forms.filepond wire:model="request.footer_logo" model="request.footer_logo" :label="__('Footer Logo')" />
                        @if (! empty($request['footer_logo']) && is_string($request['footer_logo']))
                            <img src="{{ asset('storage/'.$request['footer_logo']) }}" alt="" class="mt-3 max-h-16 rounded-lg border border-zinc-200 object-contain dark:border-zinc-700" />
                        @endif
                    </div>
                    <flux:textarea wire:model="request.footer_description" :label="__('Footer Description')" rows="4" />
                </div>
            </div>

            <div class="admin-tab-panel" @if ($selectedTab !== 'backend-tab') hidden @endif>
                <div>
                    <x-core::forms.filepond wire:model="request.admin_logo" model="request.admin_logo" :label="__('Admin Dashboard Logo')" />
                    @if (! empty($request['admin_logo']) && is_string($request['admin_logo']))
                        <img src="{{ asset('storage/'.$request['admin_logo']) }}" alt="" class="mt-3 max-h-16 rounded-lg border border-zinc-200 object-contain dark:border-zinc-700" />
                    @endif
                </div>
            </div>

            <div class="admin-tab-panel" @if ($selectedTab !== 'other-tab') hidden @endif>
                <div class="grid gap-5 sm:grid-cols-2">
                    <flux:input wire:model="request.app_base_url" :label="__('App Base URL')" class="sm:col-span-2" />
                    <flux:input wire:model="request.app_email" type="email" :label="__('From Email')" />
                    <x-core::forms.select2 wire:model="request.editor_layout" :label="__('Editor Layout')" :options="$editorOptions" />
                    <x-core::forms.select2 wire:model="request.timezone" :label="__('Timezone')" :options="$timezoneOptions" />
                    <x-core::forms.select2 wire:model="request.locale" :label="__('Locale')" :options="$localeOptions" />
                    <div class="sm:col-span-2 grid gap-4 sm:grid-cols-2">
                        <flux:switch wire:model="request.captcha" :label="__('Image Captcha')" />
                        <flux:switch wire:model="request.maintenance_mode" :label="__('Maintenance Mode')" />
                    </div>
                </div>
            </div>
        </div>

        <div class="flex justify-end border-t border-zinc-200 bg-[var(--admin-surface-muted)] px-6 py-4 dark:border-zinc-700">
            <flux:button variant="primary" type="submit">{{ __('Save Settings') }}</flux:button>
        </div>
    </form>
</div>
