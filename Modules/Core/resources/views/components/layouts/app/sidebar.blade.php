@php
    $prefs = auth()->user()?->ui_preferences ?? [];
    $themePreset = $prefs['theme_preset'] ?? 'ocean';
    $adminLayout = $prefs['admin_layout'] ?? 'floating';
    $uiRadius = $prefs['ui_radius'] ?? 'xl';
    $navbarFixed = array_key_exists('navbar_fixed', $prefs) ? (bool) $prefs['navbar_fixed'] : true;
    $sidebarCollapsed = (bool) ($prefs['sidebar_collapsed'] ?? false);
@endphp
<!DOCTYPE html>
<html
    lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    data-admin-layout="{{ $adminLayout }}"
    data-theme-preset="{{ $themePreset }}"
    data-ui-radius="{{ $uiRadius }}"
    data-navbar-fixed="{{ $navbarFixed ? 'true' : 'false' }}"
    data-sidebar-collapsed="{{ $sidebarCollapsed ? 'true' : 'false' }}"
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
                sticky
                stashable
                class="admin-sidebar !border-zinc-200 !bg-[var(--admin-surface)] dark:!border-zinc-700"
            >
                <div class="admin-sidebar-header">
                    <flux:sidebar.toggle class="admin-sidebar-close lg:hidden" icon="x-mark" />

                    <a
                        href="{{ route('admin::dashboard') }}"
                        class="admin-sidebar-brand"
                        wire:navigate
                    >
                        <span class="admin-sidebar-brand-mark">
                            <x-app-logo-icon class="size-6 fill-current text-white dark:text-black" />
                        </span>
                        <span class="admin-sidebar-brand-name">
                            {{ config('settings.site_name') ?: config('app.name') }}
                        </span>
                    </a>

                    <a
                        href="{{ route('admin::dashboard') }}"
                        class="admin-sidebar-mark"
                        wire:navigate
                        title="{{ config('app.name') }}"
                    >
                        {{ strtoupper(substr(config('settings.site_name') ?: config('app.name', 'A'), 0, 1)) }}
                    </a>
                </div>

                <x-core::layouts.app.navigation />

                <flux:spacer />

                <div class="admin-sidebar-footer">
                    <p class="text-xs leading-relaxed text-zinc-500 dark:text-zinc-400">
                        {{ __('Hello, :name. Your workspace is ready.', ['name' => auth()->user()->name]) }}
                    </p>
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
