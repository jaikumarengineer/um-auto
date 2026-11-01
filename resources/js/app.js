/**
 * Admin UI preferences bridge.
 * Server (DB via Livewire) is source of truth; localStorage is a fast cache.
 */
const STORAGE_LAYOUT = 'admin.layout';
const STORAGE_PRESET = 'admin.themePreset';
const STORAGE_RADIUS = 'admin.uiRadius';
const STORAGE_NAVBAR = 'admin.navbarFixed';
const STORAGE_SIDEBAR = 'admin.sidebarCollapsed';
const STORAGE_FONT = 'admin.uiFont';
const STORAGE_CANVAS = 'admin.canvas';
const STORAGE_MODAL_BLUR = 'admin.modalBlur';
const STORAGE_AUTH_USER = 'admin.auth.user';
const STORAGE_AUTH_HOME = 'admin.auth.home';

const VALID_LAYOUTS = ['floating', 'attached'];
const VALID_PRESETS = ['ocean', 'forest', 'slate', 'rosewood', 'midnight', 'amber', 'violet', 'indigo', 'coral', 'graphite'];
const VALID_RADII = ['none', 'xs', 'sm', 'md', 'xl'];
const VALID_FONTS = ['instrument', 'lato', 'inter', 'nunito', 'dm-sans', 'source-sans'];
const VALID_CANVASES = ['default', 'soft', 'muted', 'white', 'cool', 'warm'];

function cacheLocal({ layout, preset, uiRadius, navbarFixed, sidebarCollapsed, uiFont, canvas, modalBlur } = {}) {
    try {
        if (layout) localStorage.setItem(STORAGE_LAYOUT, layout);
        if (preset) localStorage.setItem(STORAGE_PRESET, preset);
        if (uiRadius) localStorage.setItem(STORAGE_RADIUS, uiRadius);
        if (typeof navbarFixed === 'boolean') localStorage.setItem(STORAGE_NAVBAR, navbarFixed ? '1' : '0');
        if (typeof sidebarCollapsed === 'boolean') localStorage.setItem(STORAGE_SIDEBAR, sidebarCollapsed ? '1' : '0');
        if (uiFont) localStorage.setItem(STORAGE_FONT, uiFont);
        if (canvas) localStorage.setItem(STORAGE_CANVAS, canvas);
        if (typeof modalBlur === 'boolean') localStorage.setItem(STORAGE_MODAL_BLUR, modalBlur ? '1' : '0');
    } catch {}
}

function applyToDom({ layout, preset, uiRadius, navbarFixed, sidebarCollapsed, uiFont, canvas, modalBlur } = {}) {
    const root = document.documentElement;
    if (layout && VALID_LAYOUTS.includes(layout)) root.dataset.adminLayout = layout;
    if (preset && VALID_PRESETS.includes(preset)) root.dataset.themePreset = preset;
    if (uiRadius && VALID_RADII.includes(uiRadius)) root.dataset.uiRadius = uiRadius;
    if (uiFont && VALID_FONTS.includes(uiFont)) root.dataset.uiFont = uiFont;
    if (canvas && VALID_CANVASES.includes(canvas)) root.dataset.canvas = canvas;
    if (typeof modalBlur === 'boolean') root.dataset.modalBlur = modalBlur ? 'true' : 'false';
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
            uiFont: window.AdminTheme.uiFont,
            canvas: window.AdminTheme.canvas,
            modalBlur: window.AdminTheme.modalBlur,
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
    fonts: VALID_FONTS,
    canvases: VALID_CANVASES,
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
    get uiFont() {
        return document.documentElement.dataset.uiFont || 'instrument';
    },
    get canvas() {
        return document.documentElement.dataset.canvas || 'default';
    },
    get modalBlur() {
        return document.documentElement.dataset.modalBlur !== 'false';
    },
    hydrate(state = {}, options = {}) {
        applyToDom(state);
        cacheLocal(state);
        if (window.Alpine?.store('adminTheme')) {
            Alpine.store('adminTheme').syncFrom(state);
        }
        // Callers that are just re-syncing existing state (initial paint, SPA
        // navigation) pass `silent: true` so they don't broadcast a bogus
        // 'admin-theme-changed' — listeners like the dashboard charts treat
        // that event as "the theme actually changed, destroy + redraw", and
        // firing it needlessly right after a chart is created causes it to
        // be destroyed mid-animation (a well-known Chart.js crash).
        if (! options.silent) {
            emitChange();
        }
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
    setUiFont(uiFont) {
        applyToDom({ uiFont });
        cacheLocal({ uiFont });
        if (! persistViaLivewire('setUiFont', uiFont)) emitChange();
        else emitChange();
    },
    setCanvas(canvas) {
        applyToDom({ canvas });
        cacheLocal({ canvas });
        if (! persistViaLivewire('setCanvas', canvas)) emitChange();
        else emitChange();
    },
    setModalBlur(enabled) {
        applyToDom({ modalBlur: !! enabled });
        cacheLocal({ modalBlur: !! enabled });
        if (! persistViaLivewire('setModalBlur', !! enabled)) emitChange();
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
        uiFont: window.AdminTheme.uiFont,
        canvas: window.AdminTheme.canvas,
        modalBlur: window.AdminTheme.modalBlur,
        presets: [
            { key: 'ocean', label: 'Ocean', primary: '#0d9488', secondary: '#0284c7' },
            { key: 'forest', label: 'Forest', primary: '#059669', secondary: '#d97706' },
            { key: 'slate', label: 'Slate', primary: '#475569', secondary: '#0891b2' },
            { key: 'rosewood', label: 'Rosewood', primary: '#e11d48', secondary: '#78716c' },
            { key: 'midnight', label: 'Midnight', primary: '#3f3f46', secondary: '#2563eb' },
            { key: 'amber', label: 'Amber', primary: '#d97706', secondary: '#ea580c' },
            { key: 'violet', label: 'Violet', primary: '#7c3aed', secondary: '#db2777' },
            { key: 'indigo', label: 'Indigo', primary: '#4f46e5', secondary: '#0ea5e9' },
            { key: 'coral', label: 'Coral', primary: '#f43f5e', secondary: '#f97316' },
            { key: 'graphite', label: 'Graphite', primary: '#52525b', secondary: '#71717a' },
        ],
        radii: [
            { key: 'none', label: 'None' },
            { key: 'xs', label: 'XS' },
            { key: 'sm', label: 'SM' },
            { key: 'md', label: 'MD' },
            { key: 'xl', label: 'XL' },
        ],
        fonts: [
            { key: 'instrument', label: 'Instrument Sans' },
            { key: 'lato', label: 'Lato' },
            { key: 'inter', label: 'Inter' },
            { key: 'nunito', label: 'Nunito' },
            { key: 'dm-sans', label: 'DM Sans' },
            { key: 'source-sans', label: 'Source Sans 3' },
        ],
        canvases: [
            { key: 'default', label: 'Default' },
            { key: 'soft', label: 'Soft' },
            { key: 'muted', label: 'Muted' },
            { key: 'white', label: 'White' },
            { key: 'cool', label: 'Cool' },
            { key: 'warm', label: 'Warm' },
        ],
        syncFrom(state = {}) {
            if (state.layout) this.layout = state.layout;
            if (state.preset) this.preset = state.preset;
            if (state.uiRadius) this.uiRadius = state.uiRadius;
            if (typeof state.navbarFixed === 'boolean') this.navbarFixed = state.navbarFixed;
            if (typeof state.sidebarCollapsed === 'boolean') this.sidebarCollapsed = state.sidebarCollapsed;
            if (state.uiFont) this.uiFont = state.uiFont;
            if (state.canvas) this.canvas = state.canvas;
            if (typeof state.modalBlur === 'boolean') this.modalBlur = state.modalBlur;
        },
        hydrateFromLivewire(state, options = {}) {
            window.AdminTheme.hydrate(state, options);
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
        setUiFont(uiFont) {
            window.AdminTheme.setUiFont(uiFont);
            this.uiFont = uiFont;
        },
        setCanvas(canvas) {
            window.AdminTheme.setCanvas(canvas);
            this.canvas = canvas;
        },
        setModalBlur(enabled) {
            window.AdminTheme.setModalBlur(enabled);
            this.modalBlur = !! enabled;
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

// Keep Flux collapsed-desktop attribute in sync after first paint / Livewire navigations.
// `silent: true` — this is just re-applying already-known state, not an actual
// theme change, so it must not broadcast 'admin-theme-changed' (see hydrate()).
document.addEventListener('DOMContentLoaded', () => {
    window.AdminTheme?.hydrate({
        sidebarCollapsed: window.AdminTheme.sidebarCollapsed,
    }, { silent: true });
});

document.addEventListener('livewire:navigated', () => {
    window.AdminTheme?.hydrate({
        sidebarCollapsed: window.AdminTheme.sidebarCollapsed,
    }, { silent: true });
});

// Heavy form/editor libs load asynchronously — Alpine components already wait
// via window.onAdminFormsReady() (see resources/views/partials/head.blade.php).
import('./admin-forms.js');

import Swal from 'sweetalert2';
import 'sweetalert2/dist/sweetalert2.min.css';

window.Swal = Swal;

window.AdminNotify = {
    /**
     * Map legacy toast types onto Flux variants (success | warning | danger).
     */
    fluxVariant(type = 'success') {
        switch (type) {
            case 'error':
            case 'danger':
                return 'danger';
            case 'warning':
                return 'warning';
            case 'info':
            case 'success':
            default:
                return 'success';
        }
    },

    /**
     * Lightweight alerts via Flux toast (not SweetAlert).
     */
    toast(message, type = 'success', title = null) {
        const variant = this.fluxVariant(type);
        const payload = {
            text: message,
            variant,
            duration: 3200,
        };

        if (title) {
            payload.heading = title;
        }

        if (window.Flux && typeof window.Flux.toast === 'function') {
            return window.Flux.toast(payload);
        }

        console.warn('Flux.toast is not available', payload);
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

    /**
     * Modal success/error used on save flows (users, settings).
     * When `url` is set, shows a "Back To List" cancel that navigates there.
     */
    sweetMessage({ type = 'success', title = null, message = '', url = false } = {}) {
        const icon = ['success', 'error', 'warning', 'info', 'question'].includes(type) ? type : 'error';

        if (type === 'success' && url) {
            return Swal.fire({
                title: title || 'Success',
                text: message,
                icon: 'success',
                buttonsStyling: false,
                showCancelButton: true,
                cancelButtonText: 'Back To List',
                confirmButtonText: 'OK',
                customClass: {
                    confirmButton: 'admin-swal-confirm',
                    cancelButton: 'admin-swal-cancel',
                    popup: 'admin-swal-popup',
                    actions: 'admin-swal-actions',
                },
            }).then((result) => {
                if (! result.isConfirmed && url) {
                    Livewire.navigate(url);
                }
            });
        }

        if (type === 'success') {
            return Swal.fire({
                title: title || 'Success',
                text: message,
                icon: 'success',
                buttonsStyling: false,
                confirmButtonText: 'OK',
                customClass: {
                    confirmButton: 'admin-swal-confirm',
                    popup: 'admin-swal-popup',
                },
            });
        }

        return Swal.fire({
            title: title || 'Error',
            text: message,
            icon,
            buttonsStyling: false,
            confirmButtonText: 'OK',
            customClass: {
                confirmButton: 'admin-swal-confirm',
                popup: 'admin-swal-popup',
            },
        });
    },

    /**
     * Reusable confirm dialog (delete / destructive actions).
     * Returns a Promise<boolean>. Optionally runs `onConfirm` when accepted.
     *
     * AdminNotify.confirm({ title, text, onConfirm: () => $wire.deleteUser(1) })
     */
    confirm({
        title = 'Are you sure?',
        text = 'Once deleted, you will not be able to recover this record!',
        icon = 'warning',
        confirmButtonText = 'Yes, delete it!',
        cancelButtonText = 'Cancel',
        confirmButtonClass = 'admin-swal-confirm admin-swal-confirm-danger',
        cancelButtonClass = 'admin-swal-cancel',
        onConfirm = null,
        onCancel = null,
    } = {}) {
        return Swal.fire({
            title,
            text,
            icon,
            showCancelButton: true,
            confirmButtonText,
            cancelButtonText,
            buttonsStyling: false,
            reverseButtons: true,
            focusCancel: true,
            customClass: {
                confirmButton: confirmButtonClass,
                cancelButton: cancelButtonClass,
                popup: 'admin-swal-popup',
                actions: 'admin-swal-actions',
            },
        }).then(async (result) => {
            if (result.isConfirmed) {
                if (typeof onConfirm === 'function') {
                    await onConfirm();
                }
                return true;
            }

            if (typeof onCancel === 'function') {
                await onCancel();
            }

            return false;
        });
    },
};

function normalizeNotifyDetail(raw) {
    const payload = raw ?? {};
    return Array.isArray(payload) ? (payload[0] ?? {}) : payload;
}

/**
 * Cross-tab auth fingerprint.
 * Login stamps the user id; logout clears it. Other tabs listen via `storage`
 * and leave stale pages before a refresh hits a role-gated 403.
 */
function pageAuthUser() {
    return document.documentElement.dataset.authUser ?? '';
}

function pageAuthHome() {
    return document.documentElement.dataset.authHome || '/admin';
}

function pageAuthLogin() {
    return document.documentElement.dataset.authLogin || '/admin/login';
}

function readStoredAuthUser() {
    try {
        return localStorage.getItem(STORAGE_AUTH_USER) ?? '';
    } catch {
        return '';
    }
}

function readStoredAuthHome() {
    try {
        return localStorage.getItem(STORAGE_AUTH_HOME) || pageAuthHome();
    } catch {
        return pageAuthHome();
    }
}

function writeStoredAuth(userId, home) {
    try {
        localStorage.setItem(STORAGE_AUTH_USER, userId ?? '');
        if (home) {
            localStorage.setItem(STORAGE_AUTH_HOME, home);
        }
    } catch {
        // Ignore private-mode / blocked storage.
    }
}

function redirectForAuthMismatch(storedUser) {
    if (storedUser) {
        location.replace(readStoredAuthHome());
        return;
    }

    location.replace(pageAuthLogin());
}

function checkAuthFingerprint({ fromOtherTab = false } = {}) {
    const pageUser = pageAuthUser();
    const storedUser = readStoredAuthUser();

    if (pageUser === storedUser) {
        return;
    }

    // Another tab changed auth — this document's HTML is stale.
    if (fromOtherTab) {
        redirectForAuthMismatch(storedUser);
        return;
    }

    // Same-tab load / Livewire navigate: server-rendered page is authoritative.
    writeStoredAuth(pageUser, pageUser ? pageAuthHome() : undefined);
}

window.AdminAuth = {
    stamp(userId, home) {
        writeStoredAuth(String(userId ?? ''), home || pageAuthHome());
    },
    clear() {
        writeStoredAuth('');
    },
};

window.addEventListener('storage', (event) => {
    if (event.key !== STORAGE_AUTH_USER) {
        return;
    }

    checkAuthFingerprint({ fromOtherTab: true });
});

function bootAuthFingerprint() {
    checkAuthFingerprint();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootAuthFingerprint);
} else {
    bootAuthFingerprint();
}

document.addEventListener('livewire:navigated', () => checkAuthFingerprint());
document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') {
        checkAuthFingerprint({ fromOtherTab: true });
    }
});
window.addEventListener('pageshow', (event) => {
    // bfcache restore only — normal loads already stamped via bootAuthFingerprint
    if (event.persisted) {
        checkAuthFingerprint({ fromOtherTab: true });
    }
});

document.addEventListener('livewire:init', () => {
    Livewire.on('toast', (...args) => {
        const detail = normalizeNotifyDetail(args[0]);
        window.AdminNotify.toast(detail.message ?? 'Done', detail.type ?? 'success', detail.title ?? null);
    });

    Livewire.on('swal', (...args) => {
        const detail = normalizeNotifyDetail(args[0]);
        window.AdminNotify.alert(detail.message ?? '', detail.type ?? 'success', detail.title ?? null);
    });

    // Prefer Livewire.on so each PHP $this->dispatch('SweetMessage') fires once
    // (window CustomEvent would double-trigger the same dispatch).
    Livewire.on('SweetMessage', (...args) => {
        window.AdminNotify.sweetMessage(normalizeNotifyDetail(args[0]));
    });

    Livewire.interceptRequest(({ onError }) => {
        onError(({ response, preventDefault }) => {
            const status = response?.status;

            if (status !== 403 && status !== 419) {
                return;
            }

            preventDefault();

            const storedUser = readStoredAuthUser();
            const pageUser = pageAuthUser();

            if (storedUser && storedUser !== pageUser) {
                redirectForAuthMismatch(storedUser);
                return;
            }

            if (status === 419 || ! pageUser) {
                location.replace(pageAuthLogin());
                return;
            }

            location.replace(pageAuthHome());
        });
    });
});

window.addEventListener('OpenUrlNewTab', (event) => {
    const detail = normalizeNotifyDetail(event.detail);
    if (detail.url) {
        window.open(detail.url, '_blank');
    }
});

window.dispatchEventCall = function (name = '') {
    window.dispatchEvent(new CustomEvent(name));
};

window.iirPaintReading = window.iirPaintReading || function (el) {
    if (! el) {
        return;
    }

    const minRaw = el.dataset.iirMin;
    const maxRaw = el.dataset.iirMax;
    const min = minRaw === '' || minRaw === undefined ? NaN : Number(minRaw);
    const max = maxRaw === '' || maxRaw === undefined ? NaN : Number(maxRaw);
    const text = String(el.value ?? '').trim();
    let tone = 'empty';

    if (text !== '' && Number.isFinite(min) && Number.isFinite(max)) {
        const number = Number(text);
        // Inclusive range: 55 ± 0.4 => 54.6..55.4
        tone = Number.isFinite(number) && number >= min && number <= max ? 'ok' : 'ng';
    }

    el.dataset.iirTone = tone;
    el.classList.toggle('iir-ok', tone === 'ok');
    el.classList.toggle('iir-ng', tone === 'ng');

    if (tone === 'ok') {
        el.style.setProperty('background', '#16a34a', 'important');
        el.style.setProperty('color', '#fff', 'important');
        el.style.setProperty('border-color', '#15803d', 'important');
        return;
    }

    if (tone === 'ng') {
        el.style.setProperty('background', '#dc2626', 'important');
        el.style.setProperty('color', '#fff', 'important');
        el.style.setProperty('border-color', '#b91c1c', 'important');
        return;
    }

    el.style.setProperty('background', '#fff', 'important');
    el.style.setProperty('color', '#111', 'important');
    el.style.setProperty('border-color', '#d4d4d8', 'important');
};
