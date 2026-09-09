/**
 * Admin form libraries — exposed on window for Alpine x-data components.
 */
import $ from 'jquery';
import select2 from 'select2';
import 'multiple-select/dist/multiple-select.js';
import intlTelInput from 'intl-tel-input/intlTelInputWithUtils';
import Tagify from '@yaireo/tagify';
import * as FilePond from 'filepond';
import FilePondPluginImagePreview from 'filepond-plugin-image-preview';
import FilePondPluginFileValidateType from 'filepond-plugin-file-validate-type';
import FilePondPluginFileValidateSize from 'filepond-plugin-file-validate-size';
import ClassicEditor from '@ckeditor/ckeditor5-build-classic';
import flatpickr from 'flatpickr';
import Chart from 'chart.js/auto';
import { Editor } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import TiptapUnderline from '@tiptap/extension-underline';
import TiptapLink from '@tiptap/extension-link';
import TiptapImage from '@tiptap/extension-image';
import TiptapTextAlign from '@tiptap/extension-text-align';
import TiptapPlaceholder from '@tiptap/extension-placeholder';
import TiptapTable from '@tiptap/extension-table';
import TiptapTableRow from '@tiptap/extension-table-row';
import TiptapTableCell from '@tiptap/extension-table-cell';
import TiptapTableHeader from '@tiptap/extension-table-header';
import TiptapCharacterCount from '@tiptap/extension-character-count';
import { EditorView, basicSetup } from 'codemirror';
import { EditorState } from '@codemirror/state';
import { javascript } from '@codemirror/lang-javascript';
import { html } from '@codemirror/lang-html';
import { css } from '@codemirror/lang-css';
import { json } from '@codemirror/lang-json';
import { oneDark } from '@codemirror/theme-one-dark';

import 'select2/dist/css/select2.min.css';
import 'multiple-select/dist/multiple-select.css';
import 'intl-tel-input/styles';
import '@yaireo/tagify/dist/tagify.css';
import 'filepond/dist/filepond.min.css';
import 'filepond-plugin-image-preview/dist/filepond-plugin-image-preview.css';
import 'flatpickr/dist/flatpickr.min.css';

// Select2 / multiple-select need jQuery on window
window.$ = window.jQuery = $;
select2($);

FilePond.registerPlugin(
    FilePondPluginImagePreview,
    FilePondPluginFileValidateType,
    FilePondPluginFileValidateSize,
);

function codeMirrorLanguage(mode) {
    switch (mode) {
        case 'html':
            return html();
        case 'css':
            return css();
        case 'json':
            return json();
        case 'js':
        case 'javascript':
        case 'php':
        default:
            return javascript();
    }
}

window.AdminForms = {
    $,
    select2,
    multipleSelect: $.fn.multipleSelect,
    intlTelInput,
    Tagify,
    FilePond,
    ClassicEditor,
    flatpickr,
    EditorView,
    EditorState,
    basicSetup,
    oneDark,
    codeMirrorLanguage,
    Chart,
    Tiptap: {
        Editor,
        StarterKit,
        Underline: TiptapUnderline,
        Link: TiptapLink,
        Image: TiptapImage,
        TextAlign: TiptapTextAlign,
        Placeholder: TiptapPlaceholder,
        Table: TiptapTable,
        TableRow: TiptapTableRow,
        TableCell: TiptapTableCell,
        TableHeader: TiptapTableHeader,
        CharacterCount: TiptapCharacterCount,
    },
};

/**
 * Reusable chart color helpers — reads the current admin theme CSS variables
 * so charts always match the active color preset + light/dark mode.
 */
function cssVar(name, fallback) {
    const value = getComputedStyle(document.documentElement).getPropertyValue(name).trim();
    return value || fallback;
}

window.AdminCharts = {
    Chart,
    palette() {
        const isDark = document.documentElement.classList.contains('dark');

        return {
            primary: cssVar('--admin-primary', '#0d9488'),
            secondary: cssVar('--admin-secondary', '#0284c7'),
            text: isDark ? 'rgb(161 161 170)' : 'rgb(82 82 91)',
            grid: isDark ? 'rgba(255,255,255,0.08)' : 'rgba(15,23,42,0.06)',
            surface: cssVar('--admin-surface', '#ffffff'),
        };
    },
    baseOptions(overrides = {}) {
        const palette = this.palette();

        const options = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    labels: { color: palette.text, boxWidth: 10, boxHeight: 10, usePointStyle: true },
                },
                tooltip: {
                    backgroundColor: palette.surface,
                    titleColor: palette.text,
                    bodyColor: palette.text,
                    borderColor: palette.grid,
                    borderWidth: 1,
                    padding: 10,
                    boxPadding: 4,
                },
            },
            scales: {
                x: {
                    ticks: { color: palette.text },
                    grid: { color: palette.grid, drawTicks: false },
                },
                y: {
                    ticks: { color: palette.text },
                    grid: { color: palette.grid, drawTicks: false },
                },
            },
        };

        return mergeDeep(options, overrides);
    },
};

function mergeDeep(target, source) {
    if (! source) return target;
    for (const key of Object.keys(source)) {
        if (source[key] && typeof source[key] === 'object' && ! Array.isArray(source[key])) {
            target[key] = mergeDeep(target[key] ? { ...target[key] } : {}, source[key]);
        } else {
            target[key] = source[key];
        }
    }
    return target;
}

// Signal readiness to any Alpine component that deferred its setup via
// `window.onAdminFormsReady()` (see resources/views/partials/head.blade.php).
// Livewire's own script runs synchronously near `</body>` and calls
// `Alpine.start()` immediately — before this deferred module even executes —
// so every component below must wait for this signal rather than assuming
// `window.AdminForms` / `window.AdminCharts` already exist in `init()`.
window.AdminFormsReady = true;
(window.__adminFormsReadyCallbacks || []).forEach((callback) => {
    try {
        callback();
    } catch (error) {
        console.error(error);
    }
});
window.__adminFormsReadyCallbacks = [];
