@props([
    'label' => null,
    'description' => null,
    'model' => null,
    'allowMultiple' => false,
    'acceptedFileTypes' => ['image/*'],
    'maxFileSize' => '5MB',
    'disabled' => false,
])

@php
    $name = $model ?? $attributes->whereStartsWith('wire:model')->first() ?? $attributes->get('name');
    $invalid = $name && $errors->has($name);
@endphp

<div class="admin-form-control grid gap-2">
    @if ($label)
        <flux:label>{{ $label }}</flux:label>
    @endif

    <div
        wire:ignore
        x-data="{
            pond: null,
            init() {
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
            destroy() {
                if (this.pond) {
                    window.AdminForms?.FilePond?.destroy(this.pond);
                }
            }
        }"
        x-on:remove="destroy()"
    >
        <input x-ref="input" type="file" />
    </div>

    @if ($description)
        <flux:description>{{ $description }}</flux:description>
    @endif

    @if ($invalid)
        <flux:error :name="$name" />
    @endif
</div>
