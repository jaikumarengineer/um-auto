@props([
    'method' => null,
    'params' => [],
    'title' => null,
    'text' => null,
    'icon' => 'warning',
    'confirmText' => null,
    'cancelText' => null,
    'confirmButtonClass' => 'admin-swal-confirm admin-swal-confirm-danger',
    'cancelButtonClass' => 'admin-swal-cancel',
    // Render mode: button (default Flux ghost trash) | menu-item (flux:menu.item)
    'as' => 'button',
    // Default Flux trigger button props (ignored when using a slot or as=menu-item)
    'buttonIcon' => 'trash',
    'buttonVariant' => 'ghost',
    'buttonSize' => 'sm',
    'buttonTitle' => null,
    'menuIcon' => 'trash',
    'menuLabel' => null,
])

@php
    $title = $title ?? __('Are you sure?');
    $text = $text ?? __('Once deleted, you will not be able to recover this record!');
    $confirmText = $confirmText ?? __('Yes, delete it!');
    $cancelText = $cancelText ?? __('Cancel');
    $buttonTitle = $buttonTitle ?? __('Delete');
    $menuLabel = $menuLabel ?? __('Delete');
    $params = array_values(is_array($params) ? $params : [$params]);
    $asMenuItem = $as === 'menu-item';
@endphp

{{--
    Reusable SweetAlert confirm trigger.

    Button:
        <x-core::ui.confirm-button method="deleteUser" :params="[$user->id]" />

    Menu item (inside actions-menu / flux:menu):
        <x-core::ui.confirm-button as="menu-item" method="deleteUser" :params="[$user->id]" />

    Custom trigger (slot):
        <x-core::ui.confirm-button method="archive" :params="[$id]">
            <flux:button size="sm" variant="danger">Archive</flux:button>
        </x-core::ui.confirm-button>

    JS-only:
        AdminNotify.confirm({ title: '...', onConfirm: () => $wire.deleteUser(1) })
--}}
<span
    {{ $attributes->class($asMenuItem ? 'contents' : 'inline-flex') }}
    x-data="{
        async runConfirm() {
            if (! window.AdminNotify?.confirm) {
                console.warn('AdminNotify.confirm is not loaded');
                return;
            }

            const method = @js($method);
            const params = @js($params);

            await window.AdminNotify.confirm({
                title: @js($title),
                text: @js($text),
                icon: @js($icon),
                confirmButtonText: @js($confirmText),
                cancelButtonText: @js($cancelText),
                confirmButtonClass: @js($confirmButtonClass),
                cancelButtonClass: @js($cancelButtonClass),
                onConfirm: async () => {
                    if (! method) return;
                    if (typeof $wire?.[method] === 'function') {
                        await $wire[method](...params);
                        return;
                    }
                    if (typeof $wire?.call === 'function') {
                        await $wire.call(method, ...params);
                    }
                },
            });
        },
    }"
>
    @if ($slot->isNotEmpty())
        <span class="contents" role="button" tabindex="0" x-on:click.prevent="runConfirm()" x-on:keydown.enter.prevent="runConfirm()">
            {{ $slot }}
        </span>
    @elseif ($asMenuItem)
        <flux:menu.item
            as="button"
            type="button"
            :icon="$menuIcon"
            icon:variant="outline"
            variant="danger"
            class="w-full"
            x-on:click.prevent="runConfirm()"
        >
            {{ $menuLabel }}
        </flux:menu.item>
    @else
        <span class="inline-flex" x-on:click.prevent="runConfirm()">
            <flux:button
                :size="$buttonSize"
                :variant="$buttonVariant"
                :icon="$buttonIcon"
                type="button"
                class="pointer-events-none text-red-500 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300"
                :title="$buttonTitle"
            />
        </span>
    @endif
</span>
