<?php

use Livewire\Volt\Component;

new class extends Component {
    public string $themePreset = 'ocean';

    public string $adminLayout = 'floating';

    public string $uiRadius = 'xl';

    public bool $navbarFixed = true;

    public bool $sidebarCollapsed = false;

    public string $uiFont = 'instrument';

    public string $canvas = 'default';

    public bool $modalBlur = true;

    public function mount(): void
    {
        $prefs = auth()->user()?->ui_preferences ?? [];

        $this->themePreset = $prefs['theme_preset'] ?? 'ocean';
        $this->adminLayout = $prefs['admin_layout'] ?? 'floating';
        $this->uiRadius = $prefs['ui_radius'] ?? 'xl';
        $this->navbarFixed = array_key_exists('navbar_fixed', $prefs)
            ? (bool) $prefs['navbar_fixed']
            : true;
        $this->sidebarCollapsed = (bool) ($prefs['sidebar_collapsed'] ?? false);
        $this->uiFont = $prefs['ui_font'] ?? 'instrument';
        $this->canvas = $prefs['canvas'] ?? 'default';
        $this->modalBlur = array_key_exists('modal_blur', $prefs)
            ? (bool) $prefs['modal_blur']
            : true;

        // Silent: this is just re-applying already-known saved prefs on a
        // fresh page load, not a real theme change — broadcasting the
        // 'admin-theme-changed' event here would make the dashboard charts
        // destroy + redraw themselves right as they're still playing their
        // entrance animation, which crashes Chart.js (destroy-during-animation).
        $this->applyToDocument(silent: true);
    }

    public function setPreset(string $preset): void
    {
        if (! in_array($preset, ['ocean', 'forest', 'slate', 'rosewood', 'midnight', 'amber', 'violet', 'indigo', 'coral', 'graphite'], true)) {
            return;
        }

        $this->themePreset = $preset;
        $this->persist();
    }

    public function setLayout(string $layout): void
    {
        if (! in_array($layout, ['floating', 'attached'], true)) {
            return;
        }

        $this->adminLayout = $layout;
        $this->persist();
    }

    public function setUiRadius(string $radius): void
    {
        if (! in_array($radius, ['none', 'xs', 'sm', 'md', 'xl'], true)) {
            return;
        }

        $this->uiRadius = $radius;
        $this->persist();
    }

    public function setNavbarFixed(bool $fixed): void
    {
        $this->navbarFixed = $fixed;
        $this->persist();
    }

    public function setUiFont(string $font): void
    {
        if (! in_array($font, ['instrument', 'lato', 'inter', 'nunito', 'dm-sans', 'source-sans'], true)) {
            return;
        }

        $this->uiFont = $font;
        $this->persist();
    }

    public function setCanvas(string $canvas): void
    {
        if (! in_array($canvas, ['default', 'soft', 'muted', 'white', 'cool', 'warm'], true)) {
            return;
        }

        $this->canvas = $canvas;
        $this->persist();
    }

    public function setModalBlur(bool $enabled): void
    {
        $this->modalBlur = $enabled;
        $this->persist();
    }

    public function toggleSidebar(): void
    {
        $this->sidebarCollapsed = ! $this->sidebarCollapsed;
        $this->persist();
    }

    public function setSidebarCollapsed(bool $collapsed): void
    {
        $this->sidebarCollapsed = $collapsed;
        $this->persist();
    }

    protected function persist(): void
    {
        auth()->user()?->setUiPreferences([
            'theme_preset' => $this->themePreset,
            'admin_layout' => $this->adminLayout,
            'ui_radius' => $this->uiRadius,
            'navbar_fixed' => $this->navbarFixed,
            'sidebar_collapsed' => $this->sidebarCollapsed,
            'ui_font' => $this->uiFont,
            'canvas' => $this->canvas,
            'modal_blur' => $this->modalBlur,
        ]);

        $this->applyToDocument();
    }

    protected function applyToDocument(bool $silent = false): void
    {
        $payload = [
            'preset' => $this->themePreset,
            'layout' => $this->adminLayout,
            'uiRadius' => $this->uiRadius,
            'navbarFixed' => $this->navbarFixed,
            'sidebarCollapsed' => $this->sidebarCollapsed,
            'uiFont' => $this->uiFont,
            'canvas' => $this->canvas,
            'modalBlur' => $this->modalBlur,
        ];

        $json = json_encode($payload);
        $options = json_encode(['silent' => $silent]);

        $this->js(<<<JS
            window.AdminTheme?.hydrate({$json}, {$options});
        JS);
    }
}; ?>

<div class="contents">
    {{-- Bridge Livewire state into Alpine for the offcanvas UI --}}
    <div
        x-data
        x-init="
            $store.adminTheme.hydrateFromLivewire({
                preset: @js($themePreset),
                layout: @js($adminLayout),
                uiRadius: @js($uiRadius),
                navbarFixed: @js($navbarFixed),
                sidebarCollapsed: @js($sidebarCollapsed),
                uiFont: @js($uiFont),
                canvas: @js($canvas),
                modalBlur: @js($modalBlur),
            }, { silent: true });
            window.__adminThemeLivewire = $wire;
        "
        wire:ignore.self
        class="hidden"
    ></div>
</div>
