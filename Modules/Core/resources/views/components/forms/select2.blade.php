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
    $utf8 = static function (mixed $value): string {
        $text = (string) $value;

        if ($text === '' || mb_check_encoding($text, 'UTF-8')) {
            return $text;
        }

        $converted = @mb_convert_encoding($text, 'UTF-8', 'Windows-1252');

        if (is_string($converted) && mb_check_encoding($converted, 'UTF-8')) {
            return $converted;
        }

        return iconv('UTF-8', 'UTF-8//IGNORE', $text) ?: '';
    };
    $normalized = collect($options)->map(function ($label, $value) use ($utf8) {
        if (is_array($label)) {
            return [
                'id' => $utf8($label['value'] ?? $label['id'] ?? ''),
                'text' => $utf8($label['label'] ?? $label['text'] ?? ''),
            ];
        }

        return ['id' => $utf8($value), 'text' => $utf8($label)];
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
                    // Attach to dialog when inside a Flux modal; otherwise body so
                    // overflow:hidden cards (e.g. admin-card) do not clip the menu.
                    dropdownParent: $(this.$el.closest('dialog') || document.body),
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

                let syncing = false;
                const writeWire = (val) => {
                    @if ($name)
                        if (syncing) return;
                        syncing = true;
                        const next = val === null || val === undefined ? emptyValue : val;
                        // Third arg true sends a request so wire:model.live updated* hooks run.
                        $wire.set(@js($name), next, true);
                        queueMicrotask(() => { syncing = false; });
                    @endif
                };

                $el.on('change select2:select select2:clear', () => writeWire($el.val()));
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
