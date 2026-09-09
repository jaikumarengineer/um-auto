@props(['nodes' => [], 'depth' => 0])

@foreach ($nodes as $node)
    @if (($node['type'] ?? 'item') === 'group')
        @php
            $expandable = $node['expandable'] ?? true;
        @endphp
        <flux:sidebar.group
            :heading="$node['heading']"
            :icon="$node['icon'] ?? null"
            icon:variant="outline"
            :expandable="$expandable"
            :expanded="$node['expanded'] ?? false"
            @class([
                'admin-nav-group' => $depth === 0,
                'admin-nav-heading-group' => ! $expandable,
                'admin-nav-group-nested' => $depth > 0 && $expandable,
                'admin-nav-group-deep' => $depth > 1,
            ])
        >
            {{-- A plain heading (expandable: false) is purely a visual section label —
                 its children stay at the same visual depth so they keep full icon + label
                 styling instead of the indented "sub item" treatment used for dropdowns. --}}
            <x-core::layouts.app.nav-nodes :nodes="$node['children'] ?? []" :depth="$expandable ? $depth + 1 : $depth" />
        </flux:sidebar.group>
    @else
        <flux:sidebar.item
            :icon="$depth === 0 ? ($node['icon'] ?? null) : null"
            icon:variant="outline"
            :href="route($node['route'])"
            :current="$node['active'] ?? false"
            wire:navigate
            @class([
                'admin-nav-item',
                'admin-nav-subitem' => $depth === 1,
                'admin-nav-subitem-deep' => $depth > 1,
            ])
        >
            {{ $node['label'] }}
        </flux:sidebar.item>
    @endif
@endforeach
