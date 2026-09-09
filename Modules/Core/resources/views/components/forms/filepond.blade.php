@props([
    'label' => null,
    'description' => null,
    'model' => null,
    'allowMultiple' => false,
    'acceptedFileTypes' => ['image/*'],
    'maxFileSize' => '5MB',
    'disabled' => false,
    'preview' => null,      // existing stored file URL — shown instead of a separate side preview box
    'shape' => 'rectangle',  // 'rectangle' | 'square'
    'circle' => false,       // fully round preview (e.g. avatars)
    'maxHeight' => '9rem',   // caps the preview thumbnail height
])

@php
    $name = $model ?? $attributes->whereStartsWith('wire:model')->first() ?? $attributes->get('name');
    $invalid = $name && $errors->has($name);
    $previewStyle = $shape === 'square'
        ? "height: {$maxHeight}; width: {$maxHeight};"
        : "max-height: {$maxHeight}; width: 100%; max-width: 16rem;";
    if ($circle) {
        $previewStyle .= ' border-radius: 9999px;';
    }
@endphp

<div class="admin-form-control grid gap-2">
    @if ($label)
        <flux:label>{{ $label }}</flux:label>
    @endif

    <div
        x-data="{
            pond: null,
            hasExisting: @js((bool) $preview),
            init() {
                window.onAdminFormsReady(() => this.setup());
            },
            setup() {
                const api = window.AdminForms;
                if (! api || ! @js($name)) return;
                const property = @js($name);
                this.pond = api.FilePond.create(this.$refs.input, {
                    allowMultiple: @js($allowMultiple),
                    acceptedFileTypes: @js($acceptedFileTypes),
                    maxFileSize: @js($maxFileSize),
                    disabled: @js($disabled),
                    credits: false,
                    server: {
                        process: (fieldName, file, metadata, load, error, progress, abort) => {
                            this.hasExisting = false;
                            $wire.upload(property, file,
                                () => load(file.name),
                                (err) => error(err?.message || 'Upload failed'),
                                (event) => progress(true, event.detail.progress, 100)
                            );
                            return { abort: () => { abort(); } };
                        },
                        revert: (uniqueFileId, load) => {
                            $wire.set(property, null);
                            load();
                        },
                    },
                });
            },
            removeExisting() {
                this.hasExisting = false;
                $wire.set(@js($name), null);
            },
            destroy() {
                if (this.pond) {
                    window.AdminForms?.FilePond?.destroy(this.pond);
                }
            }
        }"
        x-on:remove="destroy()"
    >
        {{-- Existing / just-saved file — shown as a compact thumbnail instead of a separate preview box --}}
        @if ($preview)
            <div
                x-show="hasExisting"
                x-cloak
                class="admin-filepond-preview admin-filepond-preview-{{ $shape }}"
                style="{{ $previewStyle }}"
            >
                <img src="{{ $preview }}" alt="" class="admin-filepond-preview-img" />

                @unless ($disabled)
                    <button
                        type="button"
                        x-on:click="removeExisting()"
                        class="admin-filepond-remove"
                        aria-label="{{ __('Remove file') }}"
                    >
                        <flux:icon.x-mark variant="outline" class="size-3.5" />
                    </button>
                @endunless
            </div>
        @endif

        <div wire:ignore x-show="! hasExisting" @if ($preview) x-cloak @endif>
            <input x-ref="input" type="file" />
        </div>
    </div>

    @if ($description)
        <flux:description>{{ $description }}</flux:description>
    @endif

    @if ($invalid)
        <flux:error :name="$name" />
    @endif
</div>
