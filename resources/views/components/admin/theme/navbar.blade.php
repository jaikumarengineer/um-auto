<flux:header
    class="sticky top-0 z-30 bg-white dark:bg-zinc-900 border-b border-zinc-200 dark:border-zinc-700 flex items-center px-4 h-16">
    <flux:sidebar.toggle class="lg:hidden mr-2" icon="bars-2" inset="left" />

    <flux:input as="button" variant="filled" placeholder="Search..." icon="magnifying-glass" class="max-w-xs mr-4" />

    <flux:spacer />

    <flux:dropdown x-data align="end">
        <flux:button variant="subtle" square class="group" aria-label="Preferred color scheme">
            <flux:icon.sun x-show="$flux.appearance === 'light'" variant="mini" class="text-zinc-500 dark:text-white" />
            <flux:icon.moon x-show="$flux.appearance === 'dark'" variant="mini" class="text-zinc-500 dark:text-white" />
            <flux:icon.moon x-show="$flux.appearance === 'system' && $flux.dark" variant="mini" />
            <flux:icon.sun x-show="$flux.appearance === 'system' && ! $flux.dark" variant="mini" />
        </flux:button>

        <flux:menu>
            <flux:menu.item icon="sun" x-on:click="$flux.appearance = 'light'">Light</flux:menu.item>
            <flux:menu.item icon="moon" x-on:click="$flux.appearance = 'dark'">Dark</flux:menu.item>
            <flux:menu.item icon="computer-desktop" x-on:click="$flux.appearance = 'system'">System</flux:menu.item>
        </flux:menu>
    </flux:dropdown>

    @php
        $notifications = [
            (object) [
                'title' => 'Welcome to the dashboard!',
                'url' => '#',
                'created_at' => now()->subMinutes(5),
            ],
            (object) [
                'title' => 'Your profile was updated.',
                'url' => '#',
                'created_at' => now()->subHours(2),
            ],
            (object) [
                'title' => 'New message received.',
                'url' => '#',
                'created_at' => now()->subDays(1),
            ],
        ];
    @endphp

    <flux:dropdown align="end" class="mr-2">
        <flux:button icon="bell" variant="subtle" />

        <flux:navmenu class="max-w-[24rem] w-[24rem]">
            <div class="px-4 py-2 border-b border-zinc-200 dark:border-zinc-700 flex items-center justify-between">
                <flux:heading size="sm">Notifications</flux:heading>
                <span class="text-xs text-zinc-500">{{ count($notifications) }} new</span>
            </div>
            <div class="overflow-y-auto max-h-[350px]">
                @php
                    // Add more notifications for demo
                    for ($i = 4; $i <= 20; $i++) {
                        $notifications[] = (object) [
                            'title' => "Notification #$i",
                            'description' => "This is a description for notification #$i.",
                            'url' => '#',
                            'created_at' => now()->subMinutes($i * 3),
                            'read' => $i % 2 === 0,
                        ];
                    }
                @endphp
                @forelse($notifications as $notification)
                    <flux:navmenu.item href="{{ $notification->url ?? '#' }}" class="flex items-start gap-3 px-4 py-3">
                        <div class="flex-1">
                            <flux:text size="sm" class="font-medium">
                                {{ $notification->title ?? 'New Notification' }}</flux:text>
                            <flux:text size="xs" class="text-zinc-500 block">
                                {{ $notification->description ?? 'No description.' }}</flux:text>
                        </div>
                        <div class="flex flex-col items-end min-w-[80px]">
                            <flux:text size="sm">
                                {{ $notification->created_at->diffForHumans() ?? '' }}
                            </flux:text>
                            <flux:badge
                                size="sm"
                                :color="!empty($notification->read) ? 'zinc' : 'red'"
                                class="mt-1"
                            >
                                {{ !empty($notification->read) ? 'Read' : 'Unread' }}
                            </flux:badge>
                        </div>
                    </flux:navmenu.item>
                @empty
                    <div class="px-4 py-3 text-zinc-500 text-sm">No notifications</div>
                @endforelse
            </div>
            <div class="border-t border-zinc-200 dark:border-zinc-700 px-4 py-2 flex justify-end">
                <flux:button as="a" href="#" size="sm" variant="subtle">View All</flux:button>
            </div>
        </flux:navmenu>
    </flux:dropdown>

    <div class="flex items-center gap-2">
        <flux:dropdown align="end">
            <flux:profile avatar="https://unavatar.io/x/calebporzio" />

            <flux:navmenu class="max-w-[12rem]">
                <div class="px-2 py-1.5">
                    <flux:text size="sm">{{ auth()->user()->full_name ?? '' }}</flux:text>
                    <flux:heading class="mt-1! truncate">{{ auth()->user()->email ?? '' }}</flux:heading>
                </div>

                <flux:navmenu.separator />

                <flux:navmenu.item href="{{ route('admin::profile') }}" icon="user"
                    class="text-zinc-800 dark:text-white">
                    Profile
                </flux:navmenu.item>

                <flux:navmenu.separator />

                <form method="POST" action="{{ route('logout') }}" class="w-full">
                    @csrf
                    <flux:navmenu.item as="button" type="submit" icon="arrow-right-start-on-rectangle"
                        class="text-zinc-800 dark:text-whit">
                        {{ __('Log Out') }}
                    </flux:navmenu.item>
                </form>
            </flux:navmenu>
        </flux:dropdown>
    </div>
</flux:header>
