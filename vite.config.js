import {
    defineConfig
} from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from "@tailwindcss/vite";

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        cors: true,
    },
    build: {
        // intl-tel-input (and similar) ship CSS nesting that esbuild rewrites with
        // :is(); the default Vite CSS target (safari14 / chrome87) cannot express that.
        cssTarget: ['chrome111', 'edge111', 'firefox111', 'safari16.4'],
        // CKEditor Classic alone exceeds the default 500 kB advisory; vendors are
        // already split below so this only covers those indivisible packages.
        chunkSizeWarningLimit: 800,
        rollupOptions: {
            output: {
                manualChunks(id) {
                    if (! id.includes('node_modules')) {
                        return;
                    }

                    if (id.includes('@ckeditor') || id.includes('ckeditor')) {
                        return 'vendor-ckeditor';
                    }

                    if (id.includes('@tiptap') || id.includes('prosemirror') || id.includes('@tiptap/pm')) {
                        return 'vendor-tiptap';
                    }

                    if (id.includes('@codemirror') || id.includes('codemirror') || id.includes('@lezer')) {
                        return 'vendor-codemirror';
                    }

                    if (id.includes('chart.js')) {
                        return 'vendor-chart';
                    }

                    if (id.includes('jquery') || id.includes('select2') || id.includes('multiple-select')) {
                        return 'vendor-jquery';
                    }

                    if (id.includes('intl-tel-input')) {
                        return 'vendor-phone';
                    }

                    if (id.includes('filepond')) {
                        return 'vendor-filepond';
                    }

                    if (id.includes('@yaireo/tagify') || id.includes('flatpickr')) {
                        return 'vendor-inputs';
                    }

                    if (id.includes('sweetalert2')) {
                        return 'vendor-swal';
                    }
                },
            },
        },
    },
});