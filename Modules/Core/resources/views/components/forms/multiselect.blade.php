@props([
    'label' => null,
    'description' => null,
    'options' => [],
    'placeholder' => 'Select',
    'filterPlaceholder' => 'Search',
    'disabled' => false,
    'updateMethod' => 'resetFilterMultiChoice',
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
@endphp

<div class="admin-form-control admin-multiselect grid gap-2">
    @if ($label)
        <flux:label>{{ $label }}</flux:label>
    @endif

    <div
        wire:ignore
        class="w-full"
        x-data="{
            $select: null,
            updateData(data) {
                if (! this.$select) return;
                const values = Array.isArray(data) ? data : [];
                this.$select.multipleSelect('uncheckAll');
                this.$select.multipleSelect('setSelects', values);
            },
            init() {
                window.onAdminFormsReady(() => this.setup());
            },
            setup() {
                const api = window.AdminForms;
                if (! api) return;
                const $ = api.$;
                this.$select = $(this.$refs.multipleSelect);

                this.$select.multipleSelect({
                    placeholder: @js($placeholder),
                    filter: true,
                    filterPlaceholder: @js($filterPlaceholder),
                    width: '100%',
                    dropWidth: '100%',
                    maxHeight: 280,
                    minimumCountSelected: 2,
                    ellipsis: false,
                    onClose: () => {
                        const selected = this.$select.multipleSelect('getSelects') || [];
                        @if ($name)
                            $wire.set(@js($name), selected);
                        @endif
                    },
                });

                queueMicrotask(() => {
                    @if ($name)
                        const current = $wire.get(@js($name)) || [];
                        this.updateData(Array.isArray(current) ? current : []);
                    @endif
                });

                this._onReset = ({ detail }) => {
                    this.updateData(detail?.data ?? []);
                };
                window.addEventListener(@js($updateMethod), this._onReset);
            },
            destroy() {
                window.removeEventListener(@js($updateMethod), this._onReset);
                try { this.$select?.multipleSelect('destroy'); } catch (e) {}
            }
        }"
        x-on:remove="destroy()"
    >
        <select
            x-ref="multipleSelect"
            multiple
            @disabled($disabled)
            class="w-full"
        >
            @foreach ($normalized as $option)
                <option value="{{ $option['id'] }}">{{ $option['text'] }}</option>
            @endforeach
            {{ $slot }}
        </select>
    </div>

    @if ($description)
        <flux:description>{{ $description }}</flux:description>
    @endif

    @if ($invalid)
        <flux:error :name="$name" />
    @endif
</div>
