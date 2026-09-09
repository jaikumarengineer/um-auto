<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>{{ $title ?? config('app.name') }}</title>

<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">

<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

{{--
    Livewire injects its own script synchronously near `</body>` and calls
    `Alpine.start()` immediately on parse — before our Vite-built `app.js`
    (a deferred ES module) has had a chance to run and set `window.AdminForms`
    / `window.AdminCharts`. Any Alpine component that reads those globals from
    its `init()` would otherwise silently no-op. This tiny ready-gate (defined
    here, synchronously, before anything else) lets those components defer
    their setup until admin-forms.js signals it's actually loaded — regardless
    of which one finishes first.
--}}
<script>
    window.AdminFormsReady = false;
    window.__adminFormsReadyCallbacks = [];
    window.onAdminFormsReady = function (callback) {
        if (window.AdminFormsReady) {
            callback();
        } else {
            window.__adminFormsReadyCallbacks.push(callback);
        }
    };
</script>

{{-- Apply defaults only when server did not already set datasets (avoid FOUC) --}}
<script>
    (function () {
        try {
            var root = document.documentElement;
            if (! root.dataset.adminLayout) {
                var layout = localStorage.getItem('admin.layout');
                root.dataset.adminLayout = (layout === 'attached' || layout === 'floating') ? layout : 'floating';
            }
            if (! root.dataset.themePreset) {
                var preset = localStorage.getItem('admin.themePreset');
                root.dataset.themePreset = ['ocean', 'forest', 'slate', 'rosewood', 'midnight'].indexOf(preset) !== -1
                    ? preset
                    : 'ocean';
            }
            if (! root.dataset.navbarFixed) {
                var navbar = localStorage.getItem('admin.navbarFixed');
                root.dataset.navbarFixed = (navbar === '0' || navbar === 'false') ? 'false' : 'true';
            }
            if (! root.dataset.sidebarCollapsed) {
                var sidebar = localStorage.getItem('admin.sidebarCollapsed');
                root.dataset.sidebarCollapsed = (sidebar === '1' || sidebar === 'true') ? 'true' : 'false';
            }
            if (! root.dataset.uiRadius) {
                var radius = localStorage.getItem('admin.uiRadius');
                root.dataset.uiRadius = ['none', 'xs', 'sm', 'md', 'xl'].indexOf(radius) !== -1
                    ? radius
                    : 'xl';
            }
        } catch (e) {
            var root = document.documentElement;
            root.dataset.adminLayout = root.dataset.adminLayout || 'floating';
            root.dataset.themePreset = root.dataset.themePreset || 'ocean';
            root.dataset.navbarFixed = root.dataset.navbarFixed || 'true';
            root.dataset.sidebarCollapsed = root.dataset.sidebarCollapsed || 'false';
            root.dataset.uiRadius = root.dataset.uiRadius || 'xl';
        }
    })();
</script>

@vite(['resources/css/app.css', 'resources/js/app.js'])

<script src="{{ asset('assets/ckeditor4/ckeditor.js') }}" data-navigate-once></script>

@fluxAppearance

@php
    $headScripts = function_exists('setting') ? (string) setting('head_scripts', '', 'scripts_third_party') : '';
    $customCss = function_exists('setting') ? (string) setting('custom_css', '', 'scripts_custom') : '';
@endphp

@if ($customCss !== '')
    <style id="admin-custom-css">{!! $customCss !!}</style>
@endif

@if ($headScripts !== '')
    {!! $headScripts !!}
@endif
