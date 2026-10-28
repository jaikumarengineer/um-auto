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
                'id' => (string) ($label['value'] ?? $label['id'] ?? ''),
                'text' => (string) ($label['label'] ?? $label['text'] ?? ''),
            ];
        }

        return ['id' => (string) $value, 'text' => (string) $label];
    })->values();

    // Select2 needs a blank option for placeholders / empty state on single selects,
    // otherwise it can show the first item visually without syncing Livewire.
    if (! $multiple && $placeholder) {
        $normalized->prepend(['id' => '', 'text' => '']);
    }
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
                window.onAdminFormsReady(() => this.setup());
            },
            setup() {
                const api = window.AdminForms;
                if (! api) return;
                const $ = api.$;
                const $el = $(this.$refs.select);
                const emptyValue = @js($multiple) ? [] : '';
                const config = {
                    width: '100%',
                    placeholder: @js($placeholder),
                    allowClear: @js($allowClear),
                    multiple: @js($multiple),
                    closeOnSelect: ! @js($multiple),
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

                const writeWire = (val) => {
                    @if ($name)
                        const next = val === null || val === undefined ? emptyValue : val;
                        $wire.set(@js($name), next);
                    @endif
                };

                $el.on('change', () => writeWire($el.val()));
                this.instance = $el;

                queueMicrotask(() => {
                    @if ($name)
                        const current = $wire.get(@js($name));
                        const next = current === undefined || current === null || current === '' ? null : current;
                        $el.val(next).trigger('change.select2');
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
