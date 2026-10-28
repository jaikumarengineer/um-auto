@props([
    'legend',
    'hint' => null,
])

{{--
  Children must declare wire:model on inputs in the Livewire view itself.
  Dynamic wire:model inside this component will not bind.
--}}
<fieldset {{ $attributes->class('space-y-3') }}>
    <legend class="text-sm font-semibold text-zinc-800 dark:text-zinc-100">{{ $legend }}</legend>
    @if ($hint)
        <p class="text-xs text-zinc-500">{{ $hint }}</p>
    @endif
    <div class="space-y-2.5">
        {{ $slot }}
    </div>
</fieldset>
