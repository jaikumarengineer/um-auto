@props([
    'label' => null,
    'description' => null,
    'mode' => 'javascript',
    'height' => '16rem',
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
            view: null,
            init() {
                const api = window.AdminForms;
                if (! api) return;
                const dark = document.documentElement.classList.contains('dark');
                const extensions = [
                    api.basicSetup,
                    api.codeMirrorLanguage(@js($mode)),
                ];
                if (dark) extensions.push(api.oneDark);
                let doc = '';
                @if ($name)
                    doc = $wire.get(@js($name)) || '';
                @endif
                this.view = new api.EditorView({
                    parent: this.$refs.host,
                    state: api.EditorState.create({
                        doc: doc,
                        extensions: [
                            ...extensions,
                            api.EditorView.updateListener.of((update) => {
                                if (! update.docChanged) return;
                                @if ($name)
                                    $wire.set(@js($name), update.state.doc.toString());
                                @endif
                            }),
                            api.EditorView.editable.of(! @js($disabled)),
                        ],
                    }),
                });
                this.$refs.host.style.minHeight = @js($height);
            },
            destroy() {
                this.view?.destroy();
            }
        }"
        x-on:remove="destroy()"
    >
        <div x-ref="host" class="cm-host overflow-hidden rounded-[var(--admin-radius-sm)] border border-zinc-200 dark:border-zinc-700"></div>
    </div>

    @if ($description)
        <flux:description>{{ $description }}</flux:description>
    @endif

    @if ($invalid)
        <flux:error :name="$name" />
    @endif
</div>
