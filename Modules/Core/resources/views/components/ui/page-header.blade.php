@props([
    'title' => null,
    'subtitle' => null,
    'breadcrumbs' => [],
])

{{--
    <x-core::ui.page-header
        :title="__('Users')"
        :subtitle="__('Manage accounts')"
        :breadcrumbs="[
            ['label' => __('Dashboard'), 'route' => 'admin::dashboard'],
            ['label' => __('Users')],
        ]"
    >
        <x-slot:actions>
            <flux:button>...</flux:button>
        </x-slot:actions>
    </x-core::ui.page-header>
--}}
<div {{ $attributes->class('admin-page-header') }}>
    @if (count($breadcrumbs) > 0)
        <x-core::ui.breadcrumbs :items="$breadcrumbs" />
    @endif

    <div class="admin-page-header-body">
        <div class="admin-page-header-row">
            <div class="admin-page-header-copy">
                @if ($title)
                    <flux:heading size="xl" level="1">{{ $title }}</flux:heading>
                @endif
                @if ($subtitle)
                    <flux:subheading>{{ $subtitle }}</flux:subheading>
                @endif
                {{ $slot }}
            </div>

            @isset($actions)
                @if (! $actions->isEmpty())
                    <div class="admin-page-header-actions">
                        {{ $actions }}
                    </div>
                @endif
            @endisset
        </div>
    </div>
</div>
