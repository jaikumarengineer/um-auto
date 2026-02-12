<flux:sidebar sticky stashable
    class="bg-zinc-50 dark:bg-zinc-900 border-r rtl:border-r-0 rtl:border-l border-zinc-200 dark:border-zinc-700">
    <flux:sidebar.toggle class="lg:hidden" icon="x-mark" />

    <flux:brand href="#" logo="https://fluxui.dev/img/demo/logo.png" name="{{ config('settings.site_name') }}"
        class="px-2 dark:hidden" />
    <flux:brand href="#" logo="https://fluxui.dev/img/demo/dark-mode-logo.png"
        name="{{ config('settings.site_name') }}" class="px-2 hidden dark:flex" />

    <flux:navlist variant="outline">

        @foreach ($menu as $groupKey => $group)
            <flux:navlist.group :heading="$group['title']" class="grid pb-2">
                @foreach ($group['menu'] as $item)
                    @if ($item['type'] === 'link')
                        <flux:navlist.item icon="{{ $item['icon'] }}" :href="$item['url']" :current="$item['active']"
                            class="mb-2" wire:navigate>
                            {{ $item['name'] }}
                        </flux:navlist.item>
                    @elseif($item['type'] === 'dropdown' && !empty($item['submenu']))
                        <flux:navlist.group expandable heading="{{ $item['name'] }}" icon="{{ $item['icon'] }}"
                            :expanded="$item['active']">
                            <x-slot name="trailing">
                                <flux:icon.chevron-down />
                            </x-slot>
                            @foreach ($item['submenu'] as $subitem)
                                <flux:navlist.item {{-- icon="{{ $subitem['icon'] }}" --}} :href="$subitem['url']"
                                    :current="$subitem['active']" wire:navigate>
                                    {{ $subitem['name'] }}
                                </flux:navlist.item>
                            @endforeach
                        </flux:navlist.group>
                    @endif
                @endforeach
            </flux:navlist.group>
        @endforeach
    </flux:navlist>

    <flux:spacer />

    <flux:navlist variant="outline">
        @foreach ($outlineMenu as $item)
            <flux:navlist.item icon="{{ $item['icon'] }}" href="{{ $item['url'] }}" target="{{ $item['target'] }}">
                {{ $item['name'] }}
            </flux:navlist.item>
        @endforeach
    </flux:navlist>
</flux:sidebar>

<!-- Mobile User Menu -->
<flux:header class="lg:hidden">
    <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

    <flux:spacer />

    <flux:dropdown position="top" align="start">
        <flux:profile :initials="auth()->user()->initials()" />

        <flux:menu>
            <flux:menu.radio.group>
                <flux:menu.radio checked>{{ auth()->user()->name }}</flux:menu.radio>
            </flux:menu.radio.group>

            <flux:menu.separator />

            <flux:menu.item :href="route('admin::settings.general')" icon="cog-6-tooth" wire:navigate>
                {{ __('Settings') }}</flux:menu.item>

            <flux:menu.separator />

            <form method="POST" action="{{ route('logout') }}" class="w-full">
                @csrf
                <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full">
                    {{ __('Log Out') }}
                </flux:menu.item>
            </form>
        </flux:menu>
    </flux:dropdown>
</flux:header>
