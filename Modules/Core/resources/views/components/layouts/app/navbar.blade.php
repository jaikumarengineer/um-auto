{{-- Admin top navbar --}}
<flux:header class="admin-topbar !border-zinc-200 !bg-[var(--admin-surface)] dark:!border-zinc-700">
    {{-- Mobile drawer --}}
    <flux:sidebar.toggle class="admin-sidebar-toggle lg:hidden" icon="bars-2" />

    {{-- Desktop sidebar collapse --}}
    <div class="admin-sidebar-toggle hidden lg:block" x-data>
        <flux:tooltip
            x-bind:content="$store.adminTheme.sidebarCollapsed ? '{{ __('Expand sidebar') }}' : '{{ __('Collapse sidebar') }}'"
            position="bottom"
        >
            <flux:button
                variant="ghost"
                size="sm"
                class="admin-icon-btn"
                x-on:click="$store.adminTheme.toggleSidebar()"
                :aria-label="__('Toggle sidebar')"
            >
                <flux:icon.bars-3 variant="outline" class="size-5 stroke-[1.5]" x-show="! $store.adminTheme.sidebarCollapsed" x-cloak />
                <flux:icon.bars-3-bottom-left variant="outline" class="size-5 stroke-[1.5]" x-show="$store.adminTheme.sidebarCollapsed" x-cloak />
            </flux:button>
        </flux:tooltip>
    </div>

    {{-- Search trigger → command palette --}}
    <flux:modal.trigger name="admin-command-search" class="ms-3 hidden min-w-0 flex-1 sm:block sm:max-w-sm lg:max-w-xl">
        <button
            type="button"
            class="admin-search-trigger flex w-full items-center gap-2"
            aria-label="{{ __('Search') }}"
        >
            <span class="admin-search-input flex w-full items-center gap-2 rounded-full border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm text-zinc-500 transition hover:border-zinc-300 hover:bg-white dark:border-zinc-700 dark:bg-zinc-900/40 dark:hover:bg-zinc-900">
                <flux:icon.magnifying-glass variant="outline" class="size-4 shrink-0 stroke-[1.5]" />
                <span class="min-w-0 flex-1 truncate text-start">{{ __('Search everything…') }}</span>
                <kbd class="hidden shrink-0 rounded-md border border-zinc-200 bg-white px-1.5 py-0.5 text-[10px] font-medium text-zinc-400 sm:inline dark:border-zinc-600 dark:bg-zinc-800">
                    ⌘K
                </kbd>
            </span>
        </button>
    </flux:modal.trigger>

    <flux:spacer />

    <div class="admin-toolbar flex items-center">
        {{-- Mobile search --}}
        <flux:modal.trigger name="admin-command-search" class="sm:hidden">
            <flux:tooltip :content="__('Search')" position="bottom">
                <button
                    type="button"
                    class="admin-icon-btn inline-flex items-center justify-center text-zinc-500 hover:text-zinc-800 dark:text-zinc-400 dark:hover:text-white"
                    aria-label="{{ __('Search') }}"
                >
                    <flux:icon.magnifying-glass variant="outline" class="size-5 stroke-[1.5]" />
                </button>
            </flux:tooltip>
        </flux:modal.trigger>

        <livewire:admin.layout.boost-menu />

        <flux:tooltip :content="__('View website')" position="bottom">
            <a
                href="{{ route('home') }}"
                target="_blank"
                class="admin-icon-btn inline-flex items-center justify-center text-zinc-500 hover:text-zinc-800 dark:text-zinc-400 dark:hover:text-white"
                aria-label="{{ __('Home') }}"
            >
                <flux:icon.home variant="outline" class="size-5 stroke-[1.5]" />
            </a>
        </flux:tooltip>

        <flux:dropdown position="bottom" align="end">
            <flux:tooltip :content="__('Notifications')" position="bottom">
                <button
                    type="button"
                    class="admin-icon-btn inline-flex items-center justify-center text-zinc-500 hover:text-zinc-800 dark:text-zinc-400 dark:hover:text-white"
                    aria-label="{{ __('Notifications') }}"
                >
                    <flux:icon.bell variant="outline" class="size-5 stroke-[1.5]" />
                </button>
            </flux:tooltip>
            <flux:menu class="admin-menu w-80 overflow-hidden rounded-2xl!">
                <div class="px-3 py-2">
                    <p class="text-sm font-semibold">{{ __('Notifications') }}</p>
                </div>
                <flux:menu.separator />
                <div class="flex flex-col items-center gap-2 px-4 py-8 text-center">
                    <flux:icon.bell variant="outline" class="size-8 text-zinc-300 dark:text-zinc-600" />
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('No notifications yet.') }}</p>
                </div>
            </flux:menu>
        </flux:dropdown>

        <div class="flex items-center" x-data>
            <flux:tooltip
                x-bind:content="$flux.appearance === 'dark' ? '{{ __('Switch to light') }}' : '{{ __('Switch to dark') }}'"
                position="bottom"
            >
                <button
                    type="button"
                    class="admin-icon-btn inline-flex items-center justify-center text-zinc-500 hover:text-zinc-800 dark:text-zinc-400 dark:hover:text-white"
                    x-on:click="$flux.appearance = ($flux.appearance === 'dark' ? 'light' : 'dark')"
                    aria-label="{{ __('Toggle appearance') }}"
                >
                    <flux:icon.moon variant="outline" class="size-5 stroke-[1.5]" x-show="$flux.appearance !== 'dark'" x-cloak />
                    <flux:icon.sun variant="outline" class="size-5 stroke-[1.5]" x-show="$flux.appearance === 'dark'" x-cloak />
                </button>
            </flux:tooltip>

            <x-core::theme-offcanvas>
                <x-slot:trigger>
                    <flux:tooltip :content="__('Theme settings')" position="bottom">
                        <button
                            type="button"
                            class="admin-icon-btn inline-flex items-center justify-center text-zinc-500 hover:text-zinc-800 dark:text-zinc-400 dark:hover:text-white"
                            aria-label="{{ __('Theme settings') }}"
                        >
                            <flux:icon.swatch variant="outline" class="size-5 stroke-[1.5]" />
                        </button>
                    </flux:tooltip>
                </x-slot:trigger>
            </x-core::theme-offcanvas>
        </div>
    </div>

    <flux:dropdown position="bottom" align="end" class="ms-3">
        <button type="button" class="flex cursor-pointer items-center gap-2 rounded-full pe-1 hover:bg-zinc-100 dark:hover:bg-zinc-800">
            <span class="flex size-9 items-center justify-center rounded-full bg-primary text-sm font-semibold text-[var(--admin-primary-foreground)]">
                {{ auth()->user()->initials() }}
            </span>
            <span class="hidden max-w-24 truncate text-sm font-medium lg:inline">{{ auth()->user()->name }}</span>
            <flux:icon.chevron-down variant="outline" class="hidden size-4 stroke-[1.5] opacity-60 lg:inline" />
        </button>
        <flux:menu class="admin-menu w-56 overflow-hidden rounded-2xl!">
            <div class="px-3 py-2">
                <p class="truncate text-sm font-semibold">{{ auth()->user()->name }}</p>
                <p class="truncate text-xs text-zinc-500">{{ auth()->user()->email }}</p>
            </div>
            <flux:menu.separator />
            <flux:menu.item :href="route('admin::profile')" icon="user-circle" icon:variant="outline" wire:navigate>
                {{ __('Profile') }}
            </flux:menu.item>
            <flux:menu.separator />
            <form method="POST" action="{{ route('logout') }}" class="w-full">
                @csrf
                <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" icon:variant="outline" class="w-full">
                    {{ __('Log Out') }}
                </flux:menu.item>
            </form>
        </flux:menu>
    </flux:dropdown>
</flux:header>
