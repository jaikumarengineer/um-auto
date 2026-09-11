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
import { Editor, mergeAttributes } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import TiptapBold from '@tiptap/extension-bold';
import TiptapItalic from '@tiptap/extension-italic';
import TiptapStrike from '@tiptap/extension-strike';
import TiptapUnderline from '@tiptap/extension-underline';
import TiptapLink from '@tiptap/extension-link';
import TiptapImage from '@tiptap/extension-image';
import TiptapTextAlign from '@tiptap/extension-text-align';
import TiptapPlaceholder from '@tiptap/extension-placeholder';
import {
    Table as TiptapTable,
    TableRow as TiptapTableRow,
    TableCell as TiptapTableCell,
    TableHeader as TiptapTableHeader,
} from '@tiptap/extension-table';
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

// TipTap v3 JSX renderHTML can be flaky with Alpine; force classic DOMOutputSpec + classes.
const AdminBold = TiptapBold.extend({
    renderHTML({ HTMLAttributes }) {
        return ['strong', mergeAttributes({ class: 'admin-tiptap-bold' }, this.options.HTMLAttributes, HTMLAttributes), 0];
    },
});

const AdminItalic = TiptapItalic.extend({
    renderHTML({ HTMLAttributes }) {
        return ['em', mergeAttributes({ class: 'admin-tiptap-italic' }, this.options.HTMLAttributes, HTMLAttributes), 0];
    },
});

const AdminStrike = TiptapStrike.extend({
    renderHTML({ HTMLAttributes }) {
        return ['s', mergeAttributes({ class: 'admin-tiptap-strike' }, this.options.HTMLAttributes, HTMLAttributes), 0];
    },
});

const AdminUnderline = TiptapUnderline.extend({
    renderHTML({ HTMLAttributes }) {
        return ['u', mergeAttributes({ class: 'admin-tiptap-underline' }, this.options.HTMLAttributes, HTMLAttributes), 0];
    },
});

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
        Bold: AdminBold,
        Italic: AdminItalic,
        Strike: AdminStrike,
        Underline: AdminUnderline,
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

/**
 * Alpine TipTap factory — kept out of Blade x-data so quotes/comments
 * cannot break HTML attribute parsing. head.blade.php ships a stub that
 * waits for this factory via onAdminFormsReady.
 */
window.__adminTiptapFactory = function adminTiptapFactory(config = {}) {
    const wireModel = config.wireModel || null;
    const placeholder = config.placeholder || 'Start writing…';
    const disabled = !! config.disabled;
    const uploadUrl = config.uploadUrl || '';
    const linkPrompt = config.linkPrompt || 'Enter URL';

    // IMPORTANT: the TipTap `Editor` instance must NOT be stored as a
    // property on the object returned below (e.g. `this.editor = ...`).
    // Alpine wraps everything in `x-data` with `@vue/reactivity`'s
    // `reactive()`, which deep-proxies any object assigned to it. When
    // ProseMirror then dispatches a transaction through that Proxy instead
    // of the raw Editor/EditorState it created internally, its identity
    // checks fail and it throws "Applying a mismatched transaction". Keeping
    // the instance in this closure variable means Alpine's reactivity never
    // touches it — only the plain reactive fields below (`active`, `chars`,
    // `canUndo`, ...) drive the UI.
    let editorInstance = null;

    return {
        debounceTimeout: null,
        chars: 0,
        words: 0,
        active: {},
        canUndo: false,
        canRedo: false,
        init() {
            window.onAdminFormsReady(() => this.setup());
        },
        setup() {
            const api = window.AdminForms?.Tiptap;
            if (! api) {
                console.warn('Tiptap is not loaded');
                return;
            }
            if (editorInstance) return;

            let initial = '';
            if (wireModel && typeof this.$wire?.get === 'function') {
                initial = this.$wire.get(wireModel) || '';
            }

            editorInstance = new api.Editor({
                element: this.$refs.content,
                extensions: [
                    api.StarterKit.configure({
                        heading: { levels: [1, 2, 3] },
                        link: false,
                        underline: false,
                        bold: false,
                        italic: false,
                        strike: false,
                    }),
                    api.Bold,
                    api.Italic,
                    api.Strike,
                    api.Underline,
                    api.Link.configure({ openOnClick: false, autolink: true }),
                    api.Image,
                    api.TextAlign.configure({ types: ['heading', 'paragraph'] }),
                    api.Placeholder.configure({
                        placeholder,
                        emptyEditorClass: 'is-editor-empty',
                        emptyNodeClass: 'is-empty',
                    }),
                    api.Table.configure({ resizable: true }),
                    api.TableRow,
                    api.TableCell,
                    api.TableHeader,
                    api.CharacterCount,
                ],
                editable: ! disabled,
                content: initial,
                editorProps: {
                    attributes: {
                        class: 'admin-tiptap-content',
                        spellcheck: 'true',
                    },
                },
                onUpdate: ({ editor }) => {
                    this.refresh();
                    if (! wireModel || typeof this.$wire?.set !== 'function') return;
                    clearTimeout(this.debounceTimeout);
                    this.debounceTimeout = setTimeout(() => {
                        this.$wire.set(wireModel, editor.getHTML());
                    }, 300);
                },
                onSelectionUpdate: () => this.refresh(),
            });

            this.$refs.content?.classList.add('admin-tiptap-content');
            this.refresh();
        },
        refresh() {
            if (! editorInstance || editorInstance.isDestroyed) return;
            const e = editorInstance;
            this.chars = e.storage.characterCount?.characters?.() ?? 0;
            this.words = e.storage.characterCount?.words?.() ?? 0;
            this.canUndo = e.can().undo();
            this.canRedo = e.can().redo();
            this.active = {
                bold: e.isActive('bold'),
                italic: e.isActive('italic'),
                underline: e.isActive('underline'),
                strike: e.isActive('strike'),
                code: e.isActive('code'),
                codeBlock: e.isActive('codeBlock'),
                blockquote: e.isActive('blockquote'),
                bulletList: e.isActive('bulletList'),
                orderedList: e.isActive('orderedList'),
                link: e.isActive('link'),
                alignLeft: e.isActive({ textAlign: 'left' }),
                alignCenter: e.isActive({ textAlign: 'center' }),
                alignRight: e.isActive({ textAlign: 'right' }),
                alignJustify: e.isActive({ textAlign: 'justify' }),
                heading: e.isActive('heading', { level: 1 })
                    ? '1'
                    : e.isActive('heading', { level: 2 })
                      ? '2'
                      : e.isActive('heading', { level: 3 })
                        ? '3'
                        : 'p',
            };
        },
        run(fn) {
            if (! editorInstance || editorInstance.isDestroyed || disabled) return;

            try {
                fn(editorInstance.chain());
            } catch (error) {
                console.warn('Tiptap command failed', error);
            }

            this.refresh();
        },
        setHeading(value) {
            if (value === 'p') {
                this.run((c) => c.setParagraph().run());
            } else {
                this.run((c) => c.toggleHeading({ level: parseInt(value, 10) }).run());
            }
        },
        setLink() {
            if (! editorInstance || editorInstance.isDestroyed) return;
            const previous = editorInstance.getAttributes('link').href || '';
            const url = window.prompt(linkPrompt, previous);
            if (url === null) return;

            // Prompt steals focus — focus belongs in the same chain.
            try {
                if (url === '') {
                    editorInstance.chain().focus().extendMarkRange('link').unsetLink().run();
                } else {
                    editorInstance.chain().focus().extendMarkRange('link').setLink({ href: url }).run();
                }
            } catch (error) {
                console.warn('Tiptap link command failed', error);
            }

            this.refresh();
        },
        pickImage() {
            this.$refs.imageInput?.click();
        },
        async uploadImage(event) {
            const file = event.target.files?.[0];
            event.target.value = '';
            if (! file || ! editorInstance || editorInstance.isDestroyed || ! uploadUrl) return;

            const formData = new FormData();
            formData.append('upload', file);

            try {
                const response = await fetch(uploadUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '',
                    },
                    body: formData,
                });
                const result = await response.json();
                if (result?.url) {
                    editorInstance.chain().focus().setImage({ src: result.url }).run();
                    this.refresh();
                }
            } catch (error) {
                console.error('Tiptap image upload failed', error);
            }
        },
        destroy() {
            clearTimeout(this.debounceTimeout);
            editorInstance?.destroy();
            editorInstance = null;
        },
    };
};

window.adminTiptap = window.__adminTiptapFactory;

// Signal readiness to any Alpine component that deferred its setup via
// `window.onAdminFormsReady()` (see resources/views/partials/head.blade.php).
window.AdminFormsReady = true;
(window.__adminFormsReadyCallbacks || []).forEach((callback) => {
    try {
        callback();
    } catch (error) {
        console.error(error);
    }
});
window.__adminFormsReadyCallbacks = [];
