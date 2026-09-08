@props([
    'label' => null,
    'description' => null,
    'placeholder' => 'Phone number',
    'preferredCountries' => ['us', 'gb', 'in', 'ae'],
    'nationalMode' => false,
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
            iti: null,
            init() {
                const api = window.AdminForms;
                if (! api) return;
                this.iti = api.intlTelInput(this.$refs.input, {
                    initialCountry: 'auto',
                    geoIpLookup: (cb) => fetch('https://ipapi.co/json').then(r => r.json()).then(d => cb(d.country_code)).catch(() => cb('us')),
                    preferredCountries: @js($preferredCountries),
                    nationalMode: @js($nationalMode),
                    separateDialCode: true,
                });
                const sync = () => {
                    const val = this.iti.isValidNumber() ? this.iti.getNumber() : this.$refs.input.value;
                    @if ($name)
                        $wire.set(@js($name), val);
                    @endif
                };
                this.$refs.input.addEventListener('change', sync);
                this.$refs.input.addEventListener('blur', sync);
                this.$refs.input.addEventListener('countrychange', sync);
                queueMicrotask(() => {
                    @if ($name)
                        const current = $wire.get(@js($name));
                        if (current) this.iti.setNumber(current);
                    @endif
                });
            },
            destroy() {
                this.iti?.destroy();
            }
        }"
        x-on:remove="destroy()"
    >
        <input
            x-ref="input"
            type="tel"
            class="iti__tel-input"
            placeholder="{{ $placeholder }}"
            @disabled($disabled)
        />
    </div>

    @if ($description)
        <flux:description>{{ $description }}</flux:description>
    @endif

    @if ($invalid)
        <flux:error :name="$name" />
    @endif
</div>
