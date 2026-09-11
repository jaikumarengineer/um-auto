<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>{{ $title ?? config('app.name') }}</title>

<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">

<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700|lato:400,500,700|inter:400,500,600,700|nunito:400,500,600,700|dm-sans:400,500,600,700|source-sans-3:400,500,600,700" rel="stylesheet" />

{{--
    Livewire injects its own script synchronously near `</body>` and calls
    `Alpine.start()` immediately on parse — before our Vite-built `app.js`
    (a deferred ES module) has had a chance to run and set `window.AdminForms`
    / `window.AdminCharts`. Any Alpine component that reads those globals from
    its `init()` would otherwise silently no-op. This tiny ready-gate (defined
    here, synchronously, before anything else) lets those components defer
    their setup until admin-forms.js signals it's actually loaded — regardless
    of which one finishes first.

    Only define once: Livewire head merges can re-evaluate this block on
    wire:navigate; resetting AdminFormsReady would strand deferred components.
--}}
<script>
    (function () {
        if (typeof window.onAdminFormsReady === 'function') {
            return;
        }

        window.AdminFormsReady = false;
        window.__adminFormsReadyCallbacks = window.__adminFormsReadyCallbacks || [];
        window.onAdminFormsReady = function (callback) {
            if (window.AdminFormsReady) {
                callback();
            } else {
                window.__adminFormsReadyCallbacks.push(callback);
            }
        };

        // Stub so Alpine can evaluate x-data="adminTiptap(...)" before admin-forms.js loads.
        window.adminTiptap = function (config) {
            return {
                debounceTimeout: null,
                chars: 0,
                words: 0,
                active: {},
                canUndo: false,
                canRedo: false,
                init() {
                    var self = this;
                    window.onAdminFormsReady(function () {
                        var factory = window.__adminTiptapFactory;
                        if (typeof factory !== 'function') {
                            console.warn('Tiptap factory is not loaded');
                            return;
                        }
                        var real = factory(config);
                        Object.keys(real).forEach(function (key) {
                            if (key === 'init') return;
                            self[key] = real[key];
                        });
                        self.setup();
                    });
                },
                run: function () {},
                setHeading: function () {},
                setLink: function () {},
                pickImage: function () {},
                uploadImage: function () {},
                destroy: function () {},
                refresh: function () {},
            };
        };
    })();
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
                root.dataset.themePreset = ['ocean', 'forest', 'slate', 'rosewood', 'midnight', 'amber', 'violet', 'indigo', 'coral', 'graphite'].indexOf(preset) !== -1
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
            if (! root.dataset.uiFont) {
                var font = localStorage.getItem('admin.uiFont');
                root.dataset.uiFont = ['instrument', 'lato', 'inter', 'nunito', 'dm-sans', 'source-sans'].indexOf(font) !== -1
                    ? font
                    : 'instrument';
            }
            if (! root.dataset.canvas) {
                var canvas = localStorage.getItem('admin.canvas');
                root.dataset.canvas = ['default', 'soft', 'muted', 'white', 'cool', 'warm'].indexOf(canvas) !== -1
                    ? canvas
                    : 'default';
            }
            if (! root.dataset.modalBlur) {
                var blur = localStorage.getItem('admin.modalBlur');
                root.dataset.modalBlur = (blur === '0' || blur === 'false') ? 'false' : 'true';
            }
        } catch (e) {
            var root = document.documentElement;
            root.dataset.adminLayout = root.dataset.adminLayout || 'floating';
            root.dataset.themePreset = root.dataset.themePreset || 'ocean';
            root.dataset.navbarFixed = root.dataset.navbarFixed || 'true';
            root.dataset.sidebarCollapsed = root.dataset.sidebarCollapsed || 'false';
            root.dataset.uiRadius = root.dataset.uiRadius || 'xl';
            root.dataset.uiFont = root.dataset.uiFont || 'instrument';
            root.dataset.canvas = root.dataset.canvas || 'default';
            root.dataset.modalBlur = root.dataset.modalBlur || 'true';
        }
    })();
</script>

@vite(['resources/css/app.css', 'resources/js/app.js'])

<script src="{{ asset('assets/ckeditor4/ckeditor.js') }}" data-navigate-once></script>

@fluxAppearance

{{--
    wire:navigate replaces <html> attributes from the response, which strips
    class="dark" (appearance is client-only). Re-apply before paint and mute
    color transitions so the shell does not blink light↔dark.
--}}
<script>
    (function () {
        if (window.__adminNavGuards) {
            return;
        }
        window.__adminNavGuards = true;

        function withMutedChromeTransitions(fn) {
            document.documentElement.classList.add('admin-navigating');
            try {
                fn();
            } finally {
                requestAnimationFrame(function () {
                    requestAnimationFrame(function () {
                        document.documentElement.classList.remove('admin-navigating');
                    });
                });
            }
        }

        // Wrap Flux appearance so light/dark toggles don't animate sticky chrome out of place.
        var originalApplyAppearance = null;
        if (window.Flux && typeof window.Flux.applyAppearance === 'function' && ! window.Flux.__adminAppearanceWrapped) {
            originalApplyAppearance = window.Flux.applyAppearance.bind(window.Flux);
            window.Flux.applyAppearance = function (appearance) {
                withMutedChromeTransitions(function () {
                    originalApplyAppearance(appearance);
                });
            };
            window.Flux.__adminAppearanceWrapped = true;
        }

        function restoreAppearance() {
            var appearance = 'system';
            try {
                appearance = window.localStorage.getItem('flux.appearance') || 'system';
            } catch (e) {}

            // Prefer the unwrapped apply during navigate — chrome is already muted.
            if (originalApplyAppearance) {
                originalApplyAppearance(appearance);
                return;
            }

            if (window.Flux && typeof window.Flux.applyAppearance === 'function') {
                window.Flux.applyAppearance(appearance);
                return;
            }

            var prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            var dark = appearance === 'dark' || (appearance !== 'light' && prefersDark);
            document.documentElement.classList.toggle('dark', dark);
        }

        function restoreSidebarCollapsedAttr() {
            var collapsed = document.documentElement.dataset.sidebarCollapsed === 'true';
            document.querySelectorAll('[data-flux-sidebar]').forEach(function (sidebar) {
                if (collapsed) {
                    sidebar.setAttribute('data-flux-sidebar-collapsed-desktop', '');
                } else {
                    sidebar.removeAttribute('data-flux-sidebar-collapsed-desktop');
                }
            });
        }

        document.addEventListener('livewire:navigate', function () {
            document.documentElement.classList.add('admin-page-leaving');
            document.documentElement.classList.remove('admin-page-enter');
        });

        document.addEventListener('alpine:navigating', function (event) {
            document.documentElement.classList.add('admin-navigating', 'admin-page-leaving');
            document.documentElement.dataset.adminNav = 'spa';
            if (event.detail && typeof event.detail.onSwap === 'function') {
                event.detail.onSwap(function () {
                    // replaceHtmlAttributes strips client-only html state — restore it
                    // before the new body paints.
                    document.documentElement.classList.add('admin-navigating');
                    document.documentElement.classList.remove('admin-page-leaving');
                    document.documentElement.dataset.adminNav = 'spa';
                    restoreAppearance();
                    restoreSidebarCollapsedAttr();
                });
            }
        });

        document.addEventListener('livewire:navigated', function () {
            restoreAppearance();
            restoreSidebarCollapsedAttr();
            document.documentElement.dataset.adminNav = 'spa';
            document.documentElement.classList.remove('admin-page-leaving');

            // Double rAF: let the new main mount at opacity 0, then play enter.
            requestAnimationFrame(function () {
                document.documentElement.classList.remove('admin-navigating');
                document.documentElement.classList.add('admin-page-enter');
                requestAnimationFrame(function () {
                    window.setTimeout(function () {
                        document.documentElement.classList.remove('admin-page-enter');
                    }, 340);
                });
            });
        });
    })();
</script>

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
