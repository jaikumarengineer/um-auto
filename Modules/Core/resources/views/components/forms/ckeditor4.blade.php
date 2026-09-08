@props([
    'label' => null,
    'description' => null,
    'height' => 320,
    'disabled' => false,
    'updateEvent' => null,
])

@php
    $name = $attributes->whereStartsWith('wire:model')->first() ?? $attributes->get('name');
    $invalid = $name && $errors->has($name);
    $uploadUrl = route('admin::editor.upload');
@endphp

<div class="admin-form-control admin-ckeditor4 grid gap-2">
    @if ($label)
        <flux:label>{{ $label }}</flux:label>
    @endif

    <div
        wire:ignore
        x-data="{
            editor: null,
            debounceTimeout: null,
            init() {
                if (typeof window.CKEDITOR === 'undefined') {
                    console.warn('CKEditor 4 is not loaded');
                    return;
                }

                this.editor = window.CKEDITOR.replace(this.$refs.input, {
                    removePlugins: 'exportpdf,autosave,save',
                    height: @js((int) $height),
                    readOnly: @js((bool) $disabled),
                    filebrowserImageBrowseUrl: '',
                    filebrowserImageUploadUrl: @js($uploadUrl) + '?type=Images&_token=' + @js(csrf_token()),
                    filebrowserBrowseUrl: '',
                    filebrowserUploadUrl: @js($uploadUrl) + '?type=Files&_token=' + @js(csrf_token()),
                });

                @if ($name)
                    queueMicrotask(() => {
                        const current = $wire.get(@js($name));
                        if (current) this.editor.setData(current);
                    });

                    this.editor.on('change', () => {
                        clearTimeout(this.debounceTimeout);
                        this.debounceTimeout = setTimeout(() => {
                            $wire.set(@js($name), this.editor.getData());
                        }, 400);
                    });
                @endif

                @if ($updateEvent)
                    this._onUpdate = ({ detail }) => {
                        if (detail?.content !== undefined) {
                            this.editor.setData(detail.content);
                        }
                    };
                    window.addEventListener(@js($updateEvent), this._onUpdate);
                @endif
            },
            destroy() {
                @if ($updateEvent)
                    window.removeEventListener(@js($updateEvent), this._onUpdate);
                @endif
                if (this.editor) {
                    try { this.editor.destroy(true); } catch (e) {}
                }
            }
        }"
        x-on:remove="destroy()"
    >
        <textarea x-ref="input" class="w-full" @disabled($disabled)></textarea>
    </div>

    @if ($description)
        <flux:description>{{ $description }}</flux:description>
    @endif

    @if ($invalid)
        <flux:error :name="$name" />
    @endif
</div>
