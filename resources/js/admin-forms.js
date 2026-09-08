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
};
