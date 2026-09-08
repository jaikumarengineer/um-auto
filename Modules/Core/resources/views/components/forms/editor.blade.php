@props([
    'label' => null,
    'description' => null,
    'placeholder' => '',
    'disabled' => false,
])

@php
    $name = $attributes->whereStartsWith('wire:model')->first() ?? $attributes->get('name');
    $invalid = $name && $errors->has($name);
@endphp

<div class="admin-form-control grid gap-2">
    @if ($label)
        <flux:label>{{ $label }}</flux:label>
    @endif

    <div
        wire:ignore
        x-data="{
            editor: null,
            init() {
                const api = window.AdminForms;
                if (! api) return;
                api.ClassicEditor.create(this.$refs.editor, {
                    placeholder: @js($placeholder),
                }).then((editor) => {
                    this.editor = editor;
                    @if ($disabled)
                        editor.enableReadOnlyMode('admin-form');
                    @endif
                    @if ($name)
                        const current = $wire.get(@js($name));
                        if (current) editor.setData(current);
                        editor.model.document.on('change:data', () => {
                            $wire.set(@js($name), editor.getData());
                        });
                    @endif
                }).catch(() => {});
            },
            destroy() {
                this.editor?.destroy().catch(() => {});
            }
        }"
        x-on:remove="destroy()"
    >
        <div x-ref="editor"></div>
    </div>

    @if ($description)
        <flux:description>{{ $description }}</flux:description>
    @endif

    @if ($invalid)
        <flux:error :name="$name" />
    @endif
</div>
