@props(['nodes' => [], 'depth' => 0])

@foreach ($nodes as $node)
    @php
        $type = $node['type'] ?? 'item';
    @endphp

    @if ($type === 'heading')
        <div @class([
            'admin-nav-heading',
            'admin-nav-heading-first' => $loop->first,
        ])>
            {{ $node['label'] ?? $node['heading'] ?? '' }}
        </div>
    @elseif ($type === 'group')
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
                'admin-nav-group-nested' => $depth > 0,
                'admin-nav-group-deep' => $depth > 1,
            ])
        >
            <x-core::layouts.app.nav-nodes :nodes="$node['children'] ?? []" :depth="$depth + 1" />
        </flux:sidebar.group>
    @else
        <flux:sidebar.item
            :icon="$depth === 0 ? ($node['icon'] ?? null) : null"
            icon:variant="outline"
            :href="route($node['route'])"
            :current="$node['active'] ?? false"
            wire:navigate.hover
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
