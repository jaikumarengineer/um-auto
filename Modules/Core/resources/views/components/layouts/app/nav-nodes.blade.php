@props(['nodes' => [], 'depth' => 0])

@foreach ($nodes as $node)
    @if (($node['type'] ?? 'item') === 'group')
        <flux:sidebar.group
            :heading="$node['heading']"
            :icon="$node['icon'] ?? null"
            icon:variant="outline"
            :expandable="$node['expandable'] ?? true"
            :expanded="$node['expanded'] ?? false"
            class="admin-nav-group"
            @class([
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
