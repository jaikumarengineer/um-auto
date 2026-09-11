@props([
    'align' => 'end',
    'position' => 'bottom',
    'icon' => 'ellipsis-vertical',
    'label' => null,
    'tooltip' => null,
    'shape' => 'square', // square | circle (icon-only)
])

@php
    $tooltip = $tooltip ?? __('Actions');
    $iconOnly = blank($label);
    $triggerClass = Flux::classes()
        ->add('admin-actions-trigger')
        ->add($iconOnly ? 'admin-actions-trigger-icon' : '')
        ->add($iconOnly && $shape === 'circle' ? 'admin-actions-trigger-circle' : '');
@endphp

{{--
    Reusable Flux actions dropdown for table rows / toolbars.

    <x-core::ui.actions-menu>
        <flux:menu.item icon="pencil-square" :href="route('...')" wire:navigate>
            {{ __('Edit') }}
        </flux:menu.item>
        <flux:menu.separator />
        <x-core::ui.confirm-button as="menu-item" method="deleteUser" :params="[$id]" />
    </x-core::ui.actions-menu>

    Icon-only circle:
    <x-core::ui.actions-menu shape="circle" />
--}}
<flux:dropdown :position="$position" :align="$align" {{ $attributes->class('inline-flex') }}>
    <flux:tooltip :content="$tooltip" position="top">
        {{--
            Icon-only must keep an empty slot (no whitespace) so Flux treats it as
            square and does not inject a trailing <span> that offsets the icon.
        --}}
        @if ($iconOnly)
            <flux:button
                type="button"
                size="sm"
                variant="ghost"
                :icon="$icon"
                icon:variant="outline"
                square
                class="{{ $triggerClass }}"
                :aria-label="$tooltip"
            />
        @else
            <flux:button
                type="button"
                size="sm"
                variant="ghost"
                :icon="$icon"
                icon:variant="outline"
                class="{{ $triggerClass }}"
                :aria-label="$tooltip"
            >{{ $label }}</flux:button>
        @endif
    </flux:tooltip>

    <flux:menu class="admin-menu admin-actions-menu overflow-hidden rounded-xl!">
        {{ $slot }}
    </flux:menu>
</flux:dropdown>
