@props([
    'label' => null,
    'description' => null,
    'placeholder' => 'Add tags…',
    'whitelist' => [],
    'enforceWhitelist' => false,
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
            tagify: null,
            init() {
                const api = window.AdminForms;
                if (! api) return;
                this.tagify = new api.Tagify(this.$refs.input, {
                    whitelist: @js($whitelist),
                    enforceWhitelist: @js($enforceWhitelist),
                    dropdown: { enabled: 0, maxItems: 20 },
                    placeholder: @js($placeholder),
                });
                const sync = () => {
                    const values = this.tagify.value.map((t) => t.value);
                    @if ($name)
                        $wire.set(@js($name), values);
                    @endif
                };
                this.tagify.on('change', sync);
                this.tagify.on('remove', sync);
                queueMicrotask(() => {
                    @if ($name)
                        const current = $wire.get(@js($name)) || [];
                        if (Array.isArray(current) && current.length) {
                            this.tagify.addTags(current);
                        }
                    @endif
                });
            },
            destroy() {
                this.tagify?.destroy();
            }
        }"
        x-on:remove="destroy()"
    >
        <input x-ref="input" type="text" @disabled($disabled) />
    </div>

    @if ($description)
        <flux:description>{{ $description }}</flux:description>
    @endif

    @if ($invalid)
        <flux:error :name="$name" />
    @endif
</div>
