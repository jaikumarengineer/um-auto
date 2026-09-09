@props([
    'label' => null,
    'description' => null,
    'mode' => 'date',
    'placeholder' => 'Select date…',
    'enableTime' => false,
    'dateFormat' => null,
    'disabled' => false,
])

@php
    $name = $attributes->whereStartsWith('wire:model')->first() ?? $attributes->get('name');
    $invalid = $name && $errors->has($name);
    $enableTime = $enableTime || in_array($mode, ['datetime', 'time'], true);
    $dateFormat = $dateFormat ?: match ($mode) {
        'time' => 'H:i',
        'datetime' => 'Y-m-d H:i',
        'range' => 'Y-m-d',
        default => 'Y-m-d',
    };
@endphp

<div class="admin-form-control grid gap-2">
    @if ($label)
        <flux:label>{{ $label }}</flux:label>
    @endif

    <div
        wire:ignore
        x-data="{
            picker: null,
            init() {
                window.onAdminFormsReady(() => this.setup());
            },
            setup() {
                const api = window.AdminForms;
                if (! api) return;
                this.picker = api.flatpickr(this.$refs.input, {
                    enableTime: @js($enableTime),
                    noCalendar: @js($mode === 'time'),
                    mode: @js($mode === 'range' ? 'range' : 'single'),
                    dateFormat: @js($dateFormat),
                    allowInput: true,
                    disableMobile: true,
                    onChange: (selectedDates, dateStr) => {
                        @if ($name)
                            $wire.set(@js($name), dateStr);
                        @endif
                    },
                });
                queueMicrotask(() => {
                    @if ($name)
                        const current = $wire.get(@js($name));
                        if (current) this.picker.setDate(current, false);
                    @endif
                });
            },
            destroy() {
                this.picker?.destroy();
            }
        }"
        x-on:remove="destroy()"
    >
        <input
            x-ref="input"
            type="text"
            class="flatpickr-input w-full"
            placeholder="{{ $placeholder }}"
            @disabled($disabled)
            readonly
        />
    </div>

    @if ($description)
        <flux:description>{{ $description }}</flux:description>
    @endif

    @if ($invalid)
        <flux:error :name="$name" />
    @endif
</div>
