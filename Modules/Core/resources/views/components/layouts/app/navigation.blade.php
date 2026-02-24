<flux:navlist variant="outline">
    @foreach($navigationItems as $group)
        <flux:navlist.group :heading="$group['heading']" class="grid">
            @foreach($group['items'] as $item)
                <flux:navlist.item
                    :icon="$item['icon']"
                    :href="route($item['route'])"
                    :current="$item['active']"
                    wire:navigate
                >
                    {{ $item['label'] }}
                </flux:navlist.item>
            @endforeach
        </flux:navlist.group>
    @endforeach
</flux:navlist>
