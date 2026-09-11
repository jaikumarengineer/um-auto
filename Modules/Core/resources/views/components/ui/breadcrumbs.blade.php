@props([
    'items' => [],
])

{{--
    <x-core::ui.breadcrumbs :items="[
        ['label' => __('Dashboard'), 'route' => 'admin::dashboard'],
        ['label' => __('Users')],
    ]" />
--}}
@if (count($items) > 0)
    @php
        $lastIndex = count($items) - 1;
    @endphp

    <nav {{ $attributes->class('admin-breadcrumbs admin-breadcrumbs-bar') }} aria-label="{{ __('Breadcrumb') }}">
        <ol class="admin-breadcrumbs-trail">
            @foreach ($items as $index => $item)
                @php
                    $label = $item['label'] ?? '';
                    $href = $item['href'] ?? null;
                    if (! $href && ! empty($item['route'])) {
                        $href = route($item['route'], $item['params'] ?? []);
                    }
                    $isFirst = $index === 0;
                    $isCurrent = $index === $lastIndex;
                    $icon = $item['icon'] ?? ($isFirst ? 'home' : null);
                @endphp

                <li class="admin-breadcrumbs-item {{ $isCurrent ? 'is-current' : '' }}">
                    @if (! $isFirst)
                        <span class="admin-breadcrumbs-sep" aria-hidden="true">
                            <flux:icon icon="chevron-right" variant="micro" class="size-3.5" />
                        </span>
                    @endif

                    @if ($href && ! $isCurrent)
                        <a href="{{ $href }}" wire:navigate class="admin-breadcrumbs-link">
                            @if ($icon)
                                <flux:icon :icon="$icon" variant="mini" class="admin-breadcrumbs-icon" />
                            @endif
                            <span>{{ $label }}</span>
                        </a>
                    @else
                        <span class="admin-breadcrumbs-current" @if ($isCurrent) aria-current="page" @endif>
                            @if ($icon && $isFirst)
                                <flux:icon :icon="$icon" variant="mini" class="admin-breadcrumbs-icon" />
                            @endif
                            <span>{{ $label }}</span>
                        </span>
                    @endif
                </li>
            @endforeach
        </ol>
    </nav>
@endif
