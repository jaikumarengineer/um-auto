@php
    $prefs = auth()->user()?->ui_preferences ?? [];
    $themePreset = $prefs['theme_preset'] ?? 'ocean';
    $adminLayout = $prefs['admin_layout'] ?? 'floating';
    $uiRadius = $prefs['ui_radius'] ?? 'xl';
    $navbarFixed = array_key_exists('navbar_fixed', $prefs) ? (bool) $prefs['navbar_fixed'] : true;
    $sidebarCollapsed = (bool) ($prefs['sidebar_collapsed'] ?? false);
    $uiFont = $prefs['ui_font'] ?? 'instrument';
    $canvas = $prefs['canvas'] ?? 'default';
    $modalBlur = array_key_exists('modal_blur', $prefs) ? (bool) $prefs['modal_blur'] : true;
@endphp
<!DOCTYPE html>
<html
    lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    data-admin-layout="{{ $adminLayout }}"
    data-theme-preset="{{ $themePreset }}"
    data-ui-radius="{{ $uiRadius }}"
    data-navbar-fixed="{{ $navbarFixed ? 'true' : 'false' }}"
    data-sidebar-collapsed="{{ $sidebarCollapsed ? 'true' : 'false' }}"
    data-ui-font="{{ $uiFont }}"
    data-canvas="{{ $canvas }}"
    data-modal-blur="{{ $modalBlur ? 'true' : 'false' }}"
>
    <head>
        @include('partials.head')
    </head>
    <body class="admin-shell min-h-screen antialiased">
        <livewire:admin.layout.theme-sync />

        <div class="admin-shell-frame">
            {{--
                Do not put Blade echoes / @if inside <flux:*> opening tags —
                it breaks Flux/Blade component compilation (orphan endif).
                Collapse attribute is synced by AdminTheme JS from data-sidebar-collapsed.
            --}}
            <flux:sidebar
                stashable
                class="admin-sidebar !border-zinc-200 !bg-[var(--admin-surface)] dark:!border-zinc-700"
            >
                <div class="admin-sidebar-header">
                    <flux:sidebar.toggle class="admin-sidebar-close lg:hidden" icon="x-mark" />

                    <a
                        href="{{ route(auth()->user()?->adminHomeRoute() ?? 'admin::dashboard') }}"
                        class="admin-sidebar-brand"
                        wire:navigate
                    >
                        <span class="admin-sidebar-brand-mark">
                            @php
                                $logoPath = setting('admin_logo', null, 'general');
                                $logoUrl = filled($logoPath) ? asset('storage/'.$logoPath) : null;
                            @endphp
                            @if ($logoUrl)
                                <img src="{{ $logoUrl }}" alt="{{ config('settings.site_name') }}" class="h-full w-full object-contain" />
                            @else
                                <x-app-logo-icon class="size-12" />
                            @endif
                        </span>
                        {{-- <span class="admin-sidebar-brand-name">
                            {{ config('settings.site_name') ?: config('app.name') }}
                        </span> --}}
                    </a>

                    <a
                        href="{{ route(auth()->user()?->adminHomeRoute() ?? 'admin::dashboard') }}"
                        class="admin-sidebar-mark"
                        wire:navigate
                        title="{{ config('app.name') }}"
                    >
                        {{ strtoupper(substr(config('settings.site_name') ?: config('app.name', 'A'), 0, 1)) }}
                    </a>
                </div>

                <x-core::layouts.app.navigation />

                <flux:spacer />
                <flux:spacer />

                <div class="admin-sidebar-footer flex items-center justify-between gap-1 hidden">
                    <flux:tooltip :content="__('Help')" position="top">
                        <a
                            href="{{ route('admin::documentation.help') }}"
                            wire:navigate
                            class="admin-icon-btn admin-sidebar-footer-btn inline-flex items-center justify-center text-zinc-500 hover:text-zinc-800 dark:text-zinc-400 dark:hover:text-white"
                            aria-label="{{ __('Help') }}"
                        >
                            <flux:icon.question-mark-circle variant="outline" class="size-4 stroke-[1.5]" />
                        </a>
                    </flux:tooltip>

                    <flux:tooltip :content="__('Changelog')" position="top">
                        <a
                            href="{{ route('admin::documentation.changelog') }}"
                            wire:navigate
                            class="admin-icon-btn admin-sidebar-footer-btn inline-flex items-center justify-center text-zinc-500 hover:text-zinc-800 dark:text-zinc-400 dark:hover:text-white"
                            aria-label="{{ __('Changelog') }}"
                        >
                            <flux:icon.document-text variant="outline" class="size-4 stroke-[1.5]" />
                        </a>
                    </flux:tooltip>

                    <flux:tooltip :content="__('Form components')" position="top">
                        <a
                            href="{{ route('admin::components.forms') }}"
                            wire:navigate
                            class="admin-icon-btn admin-sidebar-footer-btn inline-flex items-center justify-center text-zinc-500 hover:text-zinc-800 dark:text-zinc-400 dark:hover:text-white"
                            aria-label="{{ __('Form components') }}"
                        >
                            <flux:icon.squares-2x2 variant="outline" class="size-4 stroke-[1.5]" />
                        </a>
                    </flux:tooltip>

                    <flux:tooltip :content="__('Credits')" position="top">
                        <a
                            href="{{ config('app.credits_url') }}"
                            target="_blank"
                            rel="noopener"
                            class="admin-icon-btn admin-sidebar-footer-btn inline-flex items-center justify-center text-zinc-500 hover:text-zinc-800 dark:text-zinc-400 dark:hover:text-white"
                            aria-label="{{ __('Credits') }}"
                        >
                            <flux:icon.heart variant="outline" class="size-4 stroke-[1.5]" />
                        </a>
                    </flux:tooltip>

                    <span class="admin-sidebar-footer-version shrink-0 rounded-full px-2 py-1 text-[10px] font-semibold tracking-wide">
                        v1.0
                    </span>
                </div>
            </flux:sidebar>

            <x-core::layouts.app.navbar />

            {{ $slot }}
        </div>

        <x-core::layouts.app.command-search />

        <x-core::layouts.app.toast />

        @php
            $bodyScripts = function_exists('setting') ? (string) setting('body_scripts', '', 'scripts_third_party') : '';
            $customJs = function_exists('setting') ? (string) setting('custom_js', '', 'scripts_custom') : '';
        @endphp

        @if ($bodyScripts !== '')
            {!! $bodyScripts !!}
        @endif

        @if ($customJs !== '')
            <script id="admin-custom-js">{!! $customJs !!}</script>
        @endif

        @fluxScripts
    </body>
</html>
