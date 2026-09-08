@props([
    'label' => null,
    'description' => null,
    'options' => [],
    'multiple' => false,
    'placeholder' => 'Select…',
    'allowClear' => true,
    'disabled' => false,
    'ajaxUrl' => null,
])

@php
    $name = $attributes->whereStartsWith('wire:model')->first() ?? $attributes->get('name');
    $invalid = $name && $errors->has($name);
    $normalized = collect($options)->map(function ($label, $value) {
        if (is_array($label)) {
            return [
                'id' => $label['value'] ?? $label['id'] ?? '',
                'text' => $label['label'] ?? $label['text'] ?? '',
            ];
        }

        return ['id' => (string) $value, 'text' => (string) $label];
    })->values();
@endphp

<div class="admin-form-control admin-select2 grid gap-2">
    @if ($label)
        <flux:label :badge="$attributes->get('badge')">{{ $label }}</flux:label>
    @endif

    <div
        wire:ignore
        class="admin-select2-shell relative w-full"
        x-data="{
            instance: null,
            init() {
                const api = window.AdminForms;
                if (! api) return;
                const $ = api.$;
                const $el = $(this.$refs.select);
                const config = {
                    width: '100%',
                    placeholder: @js($placeholder),
                    allowClear: @js($allowClear),
                    multiple: @js($multiple),
                    data: @js($normalized),
                    dropdownParent: $(this.$el),
                    selectionCssClass: 'admin-select2-selection',
                    dropdownCssClass: 'admin-select2-dropdown',
                };
                @if ($ajaxUrl)
                    config.ajax = {
                        url: @js($ajaxUrl),
                        dataType: 'json',
                        delay: 250,
                        data: (params) => ({ q: params.term }),
                        processResults: (data) => ({
                            results: (data.results || data).map((row) => ({
                                id: row.id ?? row.value,
                                text: row.text ?? row.label ?? row.name,
                            })),
                        }),
                    };
                @endif
                $el.select2(config);
                const sync = () => {
                    const val = $el.val();
                    @if ($name)
                        $wire.set(@js($name), val ?? (@js($multiple) ? [] : null));
                    @endif
                };
                $el.on('change', sync);
                this.instance = $el;
                queueMicrotask(() => {
                    @if ($name)
                        const current = $wire.get(@js($name));
                        if (current !== undefined && current !== null && current !== '') {
                            $el.val(current).trigger('change.select2');
                        }
                    @endif
                });
            },
            destroy() {
                if (this.instance) {
                    try { this.instance.select2('destroy'); } catch (e) {}
                }
            }
        }"
        x-on:remove="destroy()"
    >
        <select
            x-ref="select"
            @if ($multiple) multiple @endif
            @disabled($disabled)
            class="w-full"
        ></select>
    </div>

    @if ($description)
        <flux:description>{{ $description }}</flux:description>
    @endif

    @if ($invalid)
        <flux:error :name="$name" />
    @endif
</div>
