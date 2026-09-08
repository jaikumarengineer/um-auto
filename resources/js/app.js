/**
 * Admin UI preferences bridge.
 * Server (DB via Livewire) is source of truth; localStorage is a fast cache.
 */
const STORAGE_LAYOUT = 'admin.layout';
const STORAGE_PRESET = 'admin.themePreset';
const STORAGE_RADIUS = 'admin.uiRadius';
const STORAGE_NAVBAR = 'admin.navbarFixed';
const STORAGE_SIDEBAR = 'admin.sidebarCollapsed';
const VALID_LAYOUTS = ['floating', 'attached'];
const VALID_PRESETS = ['ocean', 'forest', 'slate', 'rosewood', 'midnight'];
const VALID_RADII = ['none', 'xs', 'sm', 'md', 'xl'];

function cacheLocal({ layout, preset, uiRadius, navbarFixed, sidebarCollapsed } = {}) {
    try {
        if (layout) localStorage.setItem(STORAGE_LAYOUT, layout);
        if (preset) localStorage.setItem(STORAGE_PRESET, preset);
        if (uiRadius) localStorage.setItem(STORAGE_RADIUS, uiRadius);
        if (typeof navbarFixed === 'boolean') localStorage.setItem(STORAGE_NAVBAR, navbarFixed ? '1' : '0');
        if (typeof sidebarCollapsed === 'boolean') localStorage.setItem(STORAGE_SIDEBAR, sidebarCollapsed ? '1' : '0');
    } catch {}
}

function applyToDom({ layout, preset, uiRadius, navbarFixed, sidebarCollapsed } = {}) {
    const root = document.documentElement;
    if (layout && VALID_LAYOUTS.includes(layout)) root.dataset.adminLayout = layout;
    if (preset && VALID_PRESETS.includes(preset)) root.dataset.themePreset = preset;
    if (uiRadius && VALID_RADII.includes(uiRadius)) root.dataset.uiRadius = uiRadius;
    if (typeof navbarFixed === 'boolean') root.dataset.navbarFixed = navbarFixed ? 'true' : 'false';
    if (typeof sidebarCollapsed === 'boolean') {
        root.dataset.sidebarCollapsed = sidebarCollapsed ? 'true' : 'false';
        document.querySelectorAll('[data-flux-sidebar]').forEach((sidebar) => {
            if (sidebarCollapsed) {
                sidebar.setAttribute('data-flux-sidebar-collapsed-desktop', '');
            } else {
                sidebar.removeAttribute('data-flux-sidebar-collapsed-desktop');
            }
        });
    }
}

function emitChange() {
    window.dispatchEvent(new CustomEvent('admin-theme-changed', {
        detail: {
            layout: window.AdminTheme.layout,
            preset: window.AdminTheme.preset,
            uiRadius: window.AdminTheme.uiRadius,
            navbarFixed: window.AdminTheme.navbarFixed,
            sidebarCollapsed: window.AdminTheme.sidebarCollapsed,
        },
    }));
}

function persistViaLivewire(method, value) {
    const wire = window.__adminThemeLivewire;
    if (! wire || typeof wire[method] !== 'function') return false;
    wire[method](value);
    return true;
}

window.AdminTheme = {
    layouts: VALID_LAYOUTS,
    presets: VALID_PRESETS,
    radii: VALID_RADII,
    get layout() {
        return document.documentElement.dataset.adminLayout || 'floating';
    },
    get preset() {
        return document.documentElement.dataset.themePreset || 'ocean';
    },
    get uiRadius() {
        return document.documentElement.dataset.uiRadius || 'xl';
    },
    get navbarFixed() {
        return document.documentElement.dataset.navbarFixed !== 'false';
    },
    get sidebarCollapsed() {
        return document.documentElement.dataset.sidebarCollapsed === 'true';
    },
    hydrate(state = {}) {
        applyToDom(state);
        cacheLocal(state);
        if (window.Alpine?.store('adminTheme')) {
            Alpine.store('adminTheme').syncFrom(state);
        }
        emitChange();
    },
    setLayout(layout) {
        applyToDom({ layout });
        cacheLocal({ layout });
        if (! persistViaLivewire('setLayout', layout)) emitChange();
        else emitChange();
    },
    setPreset(preset) {
        applyToDom({ preset });
        cacheLocal({ preset });
        if (! persistViaLivewire('setPreset', preset)) emitChange();
        else emitChange();
    },
    setUiRadius(uiRadius) {
        applyToDom({ uiRadius });
        cacheLocal({ uiRadius });
        if (! persistViaLivewire('setUiRadius', uiRadius)) emitChange();
        else emitChange();
    },
    setNavbarFixed(fixed) {
        applyToDom({ navbarFixed: !! fixed });
        cacheLocal({ navbarFixed: !! fixed });
        if (! persistViaLivewire('setNavbarFixed', !! fixed)) emitChange();
        else emitChange();
    },
    toggleSidebar() {
        const next = ! this.sidebarCollapsed;
        applyToDom({ sidebarCollapsed: next });
        cacheLocal({ sidebarCollapsed: next });
        if (! persistViaLivewire('setSidebarCollapsed', next)) emitChange();
        else emitChange();
    },
    setSidebarCollapsed(collapsed) {
        applyToDom({ sidebarCollapsed: !! collapsed });
        cacheLocal({ sidebarCollapsed: !! collapsed });
        if (! persistViaLivewire('setSidebarCollapsed', !! collapsed)) emitChange();
        else emitChange();
    },
};

function registerAdminThemeStore() {
    if (! window.Alpine || Alpine.store('adminTheme')) return;

    Alpine.store('adminTheme', {
        layout: window.AdminTheme.layout,
        preset: window.AdminTheme.preset,
        uiRadius: window.AdminTheme.uiRadius,
        navbarFixed: window.AdminTheme.navbarFixed,
        sidebarCollapsed: window.AdminTheme.sidebarCollapsed,
        presets: [
            { key: 'ocean', label: 'Ocean', primary: '#0d9488', secondary: '#0284c7' },
            { key: 'forest', label: 'Forest', primary: '#059669', secondary: '#d97706' },
            { key: 'slate', label: 'Slate', primary: '#475569', secondary: '#0891b2' },
            { key: 'rosewood', label: 'Rosewood', primary: '#e11d48', secondary: '#78716c' },
            { key: 'midnight', label: 'Midnight', primary: '#3f3f46', secondary: '#2563eb' },
        ],
        radii: [
            { key: 'none', label: 'None' },
            { key: 'xs', label: 'XS' },
            { key: 'sm', label: 'SM' },
            { key: 'md', label: 'MD' },
            { key: 'xl', label: 'XL' },
        ],
        syncFrom(state = {}) {
            if (state.layout) this.layout = state.layout;
            if (state.preset) this.preset = state.preset;
            if (state.uiRadius) this.uiRadius = state.uiRadius;
            if (typeof state.navbarFixed === 'boolean') this.navbarFixed = state.navbarFixed;
            if (typeof state.sidebarCollapsed === 'boolean') this.sidebarCollapsed = state.sidebarCollapsed;
        },
        hydrateFromLivewire(state) {
            window.AdminTheme.hydrate(state);
            this.syncFrom(state);
        },
        setLayout(layout) {
            window.AdminTheme.setLayout(layout);
            this.layout = layout;
        },
        setPreset(preset) {
            window.AdminTheme.setPreset(preset);
            this.preset = preset;
        },
        setUiRadius(uiRadius) {
            window.AdminTheme.setUiRadius(uiRadius);
            this.uiRadius = uiRadius;
        },
        setNavbarFixed(fixed) {
            window.AdminTheme.setNavbarFixed(fixed);
            this.navbarFixed = !! fixed;
        },
        toggleSidebar() {
            window.AdminTheme.toggleSidebar();
            this.sidebarCollapsed = window.AdminTheme.sidebarCollapsed;
        },
    });
}

document.addEventListener('alpine:init', () => {
    registerAdminThemeStore();
    window.addEventListener('admin-theme-changed', (event) => {
        Alpine.store('adminTheme')?.syncFrom(event.detail || {});
    });
});

if (window.Alpine) registerAdminThemeStore();

// Keep Flux collapsed-desktop attribute in sync after first paint / Livewire navigations
document.addEventListener('DOMContentLoaded', () => {
    window.AdminTheme?.hydrate({
        sidebarCollapsed: window.AdminTheme.sidebarCollapsed,
    });
});

document.addEventListener('livewire:navigated', () => {
    window.AdminTheme?.hydrate({
        sidebarCollapsed: window.AdminTheme.sidebarCollapsed,
    });
});

import './admin-forms.js';
import Swal from 'sweetalert2';
import 'sweetalert2/dist/sweetalert2.min.css';

window.Swal = Swal;

window.AdminNotify = {
    toast(message, type = 'success', title = null) {
        const icon = ['success', 'error', 'warning', 'info', 'question'].includes(type) ? type : 'info';

        return Swal.fire({
            toast: true,
            position: 'bottom-end',
            icon,
            title: title || message,
            text: title ? message : undefined,
            showConfirmButton: false,
            timer: 3200,
            timerProgressBar: true,
            customClass: {
                popup: 'admin-swal-toast',
            },
        });
    },

    alert(message, type = 'success', title = null) {
        const icon = ['success', 'error', 'warning', 'info', 'question'].includes(type) ? type : 'info';

        return Swal.fire({
            icon,
            title: title || (icon === 'success' ? 'Success' : icon === 'error' ? 'Error' : 'Notice'),
            text: message,
            confirmButtonText: 'OK',
            buttonsStyling: false,
            customClass: {
                confirmButton: 'admin-swal-confirm',
                popup: 'admin-swal-popup',
            },
        });
    },
};

document.addEventListener('livewire:init', () => {
    Livewire.on('toast', (...args) => {
        const payload = args[0] ?? {};
        const detail = Array.isArray(payload) ? (payload[0] ?? {}) : payload;
        window.AdminNotify.toast(detail.message ?? 'Done', detail.type ?? 'success', detail.title ?? null);
    });

    Livewire.on('swal', (...args) => {
        const payload = args[0] ?? {};
        const detail = Array.isArray(payload) ? (payload[0] ?? {}) : payload;
        window.AdminNotify.alert(detail.message ?? '', detail.type ?? 'success', detail.title ?? null);
    });
});
