@php
    $flatten = function (array $nodes, ?string $group = null) use (&$flatten): array {
        $items = [];

        foreach ($nodes as $node) {
            if (($node['type'] ?? 'item') === 'group') {
                $items = array_merge(
                    $items,
                    $flatten($node['children'] ?? [], $node['heading'] ?? $group)
                );
                continue;
            }

            if (empty($node['route'])) {
                continue;
            }

            $items[] = [
                'label' => (string) $node['label'],
                'group' => (string) ($group ?? __('Navigation')),
                'icon' => $node['icon'] ?? 'arrow-right',
                'href' => route($node['route']),
                'keywords' => strtolower(($node['label'] ?? '').' '.($group ?? '')),
                'action' => null,
            ];
        }

        return $items;
    };

    $navComponent = new \Modules\Core\View\Components\Layouts\App\Navigation;
    $navItems = $flatten($navComponent->navigationItems);

    $catalog = array_values(array_merge([
        [
            'label' => __('Dashboard'),
            'group' => __('Suggestions'),
            'icon' => 'home',
            'href' => route('admin::dashboard'),
            'keywords' => 'dashboard home overview',
            'action' => null,
        ],
        [
            'label' => __('Search users'),
            'group' => __('Suggestions'),
            'icon' => 'magnifying-glass',
            'href' => route('admin::users.list'),
            'keywords' => 'search users people accounts',
            'action' => null,
        ],
        [
            'label' => __('Manage users'),
            'group' => __('Suggestions'),
            'icon' => 'users',
            'href' => route('admin::users.list'),
            'keywords' => 'users list manage',
            'action' => null,
        ],
        [
            'label' => __('Theme studio'),
            'group' => __('Suggestions'),
            'icon' => 'swatch',
            'href' => '#',
            'keywords' => 'theme appearance colors layout',
            'action' => 'theme',
        ],
        [
            'label' => __('My Profile'),
            'group' => __('Account'),
            'icon' => 'user-circle',
            'href' => route('admin::profile'),
            'keywords' => 'account profile password avatar',
            'action' => null,
        ],
        [
            'label' => __('General settings'),
            'group' => __('Settings'),
            'icon' => 'adjustments-horizontal',
            'href' => route('admin::settings.general'),
            'keywords' => 'general settings',
            'action' => null,
        ],
    ], $navItems));

    $seen = [];
    $catalog = array_values(array_filter($catalog, function ($item) use (&$seen) {
        $key = strtolower($item['label'].'|'.$item['href'].'|'.($item['action'] ?? ''));
        if (isset($seen[$key])) {
            return false;
        }
        $seen[$key] = true;

        return true;
    }));

    $grouped = collect($catalog)->groupBy('group');
@endphp

<div
    x-data="{
        query: '',
        active: 0,
        open() {
            this.query = '';
            this.active = 0;
            this.$dispatch('modal-show', { name: 'admin-command-search' });
        },
        close() {
            this.$dispatch('modal-close', { name: 'admin-command-search' });
        },
        onShown() {
            this.query = '';
            this.active = 0;
            this.$nextTick(() => {
                this.refreshActive();
                this.$refs.query?.focus();
            });
        },
        visibleButtons() {
            return Array.from(this.$refs.list?.querySelectorAll('[data-command-item]') || [])
                .filter((el) => el.offsetParent !== null);
        },
        refreshActive() {
            const buttons = this.visibleButtons();
            if (! buttons.length) {
                this.active = 0;
                return;
            }
            if (this.active >= buttons.length) this.active = 0;
            buttons.forEach((btn, i) => {
                btn.dataset.active = i === this.active ? 'true' : 'false';
                btn.classList.toggle('is-active', i === this.active);
            });
            buttons[this.active]?.scrollIntoView({ block: 'nearest' });
        },
        move(delta) {
            const buttons = this.visibleButtons();
            if (! buttons.length) return;
            this.active = (this.active + delta + buttons.length) % buttons.length;
            this.refreshActive();
        },
        selectActive() {
            this.visibleButtons()[this.active]?.click();
        },
        matches(keywords, label, group) {
            const q = this.query.trim().toLowerCase();
            if (! q) return true;
            return (keywords + ' ' + label + ' ' + group).toLowerCase().includes(q);
        },
        run(action, href) {
            if (action === 'theme') {
                this.close();
                this.$nextTick(() => this.$dispatch('modal-show', { name: 'admin-theme-panel' }));
                return;
            }
            this.close();
            if (window.Livewire?.navigate) {
                window.Livewire.navigate(href);
            } else {
                window.location.href = href;
            }
        }
    }"
    x-on:keydown.ctrl.k.window.prevent="open()"
    x-on:keydown.meta.k.window.prevent="open()"
    x-on:modal-show.document="if ($event.detail?.name === 'admin-command-search') onShown()"
    class="contents"
>
    <flux:modal
        name="admin-command-search"
        class="admin-command-search !max-w-xl !p-0 overflow-hidden dark:!bg-[var(--admin-surface)]"
    >
        <div
            class="flex flex-col"
            x-on:keydown.arrow-down.prevent="move(1)"
            x-on:keydown.arrow-up.prevent="move(-1)"
            x-on:keydown.enter.prevent="selectActive()"
        >
            <div class="flex items-center gap-3 border-b border-zinc-200 px-4 py-3 dark:border-zinc-700">
                <flux:icon.magnifying-glass variant="outline" class="size-5 shrink-0 text-zinc-400" />
                <input
                    x-ref="query"
                    type="search"
                    x-model="query"
                    x-on:input="active = 0; $nextTick(() => refreshActive())"
                    placeholder="{{ __('Type a command or search…') }}"
                    class="min-w-0 flex-1 border-0 bg-transparent text-base text-zinc-800 outline-none placeholder:text-zinc-400 focus:ring-0 dark:text-zinc-100"
                    autocomplete="off"
                    spellcheck="false"
                />
                <button
                    type="button"
                    class="inline-flex size-8 items-center justify-center rounded-lg text-zinc-400 hover:bg-zinc-100 hover:text-zinc-700 dark:hover:bg-zinc-800 dark:hover:text-zinc-200"
                    x-on:click="close()"
                    aria-label="{{ __('Close') }}"
                >
                    <flux:icon.x-mark variant="outline" class="size-5" />
                </button>
            </div>

            <div x-ref="list" class="admin-command-search-list max-h-[22rem] overflow-y-auto p-2">
                @foreach ($grouped as $group => $items)
                    @php
                        $matchArgs = $items->map(fn ($item) => [
                            'keywords' => $item['keywords'],
                            'label' => $item['label'],
                            'group' => $item['group'],
                        ])->values();
                    @endphp
                    <div
                        class="mb-2"
                        x-show="@js($matchArgs).some((item) => matches(item.keywords, item.label, item.group))"
                    >
                        <p class="px-2 pb-1 pt-2 text-[11px] font-semibold uppercase tracking-wider text-zinc-400">
                            {{ $group }}
                        </p>

                        @foreach ($items as $item)
                            <button
                                type="button"
                                data-command-item
                                class="admin-command-item flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-start text-sm text-zinc-700 transition hover:bg-zinc-100 dark:text-zinc-200 dark:hover:bg-zinc-800"
                                x-show="matches(@js($item['keywords']), @js($item['label']), @js($item['group']))"
                                x-on:mouseenter="active = visibleButtons().indexOf($el); refreshActive()"
                                x-on:click="run(@js($item['action']), @js($item['href']))"
                            >
                                <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-zinc-100 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-300">
                                    <flux:icon :icon="$item['icon']" variant="outline" class="size-4" />
                                </span>
                                <span class="min-w-0 flex-1 truncate font-medium">{{ $item['label'] }}</span>
                            </button>
                        @endforeach
                    </div>
                @endforeach

                <div
                    class="px-3 py-10 text-center text-sm text-zinc-500"
                    x-show="visibleButtons().length === 0"
                    x-cloak
                >
                    {{ __('No results found.') }}
                </div>
            </div>

            <div class="flex items-center justify-between gap-3 border-t border-zinc-200 px-4 py-2.5 text-[11px] text-zinc-400 dark:border-zinc-700">
                <span>{{ __('Navigate with ↑ ↓ · Open with Enter') }}</span>
                <span class="inline-flex items-center gap-1">
                    <kbd class="rounded border border-zinc-200 px-1.5 py-0.5 dark:border-zinc-600">Esc</kbd>
                    {{ __('to close') }}
                </span>
            </div>
        </div>
    </flux:modal>
</div>
