@props([
    'name' => 'admin-theme-panel',
    'position' => 'right',
    'withTrigger' => true,
])

@if ($withTrigger)
    <flux:modal.trigger :name="$name">
        @isset($trigger)
            {{ $trigger }}
        @else
            <flux:tooltip :content="__('Theme settings')" position="bottom">
                <flux:button
                    variant="ghost"
                    size="sm"
                    class="admin-icon-btn !h-9 !w-9 rounded-full!"
                    icon="swatch"
                    :aria-label="__('Theme settings')"
                />
            </flux:tooltip>
        @endisset
    </flux:modal.trigger>
@endif

<flux:modal
    :name="$name"
    variant="flyout"
    :position="$position"
    class="admin-theme-offcanvas !rounded-s-3xl dark:!bg-[var(--admin-surface)]"
    x-data
>
    <div class="flex h-full flex-col gap-6">
        <div>
            <flux:heading size="lg">{{ __('Theme studio') }}</flux:heading>
            <flux:subheading>{{ __('Appearance, layout, corners, colors, and navbar behavior.') }}</flux:subheading>
        </div>

        <section class="space-y-3">
            <p class="text-xs font-semibold uppercase tracking-wider text-zinc-500">{{ __('Appearance') }}</p>
            <flux:radio.group variant="segmented" x-model="$flux.appearance" class="w-full">
                <flux:radio value="light" icon="sun">{{ __('Light') }}</flux:radio>
                <flux:radio value="dark" icon="moon">{{ __('Dark') }}</flux:radio>
                <flux:radio value="system" icon="computer-desktop">{{ __('System') }}</flux:radio>
            </flux:radio.group>
        </section>

        <section class="space-y-3">
            <p class="text-xs font-semibold uppercase tracking-wider text-zinc-500">{{ __('Shell layout') }}</p>
            <div class="grid grid-cols-2 gap-2">
                <button
                    type="button"
                    class="rounded-2xl border px-3 py-3 text-start transition"
                    x-on:click="$store.adminTheme.setLayout('floating')"
                    x-bind:class="$store.adminTheme.layout === 'floating'
                        ? 'border-primary bg-primary/10 text-primary'
                        : 'border-zinc-200 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800'"
                >
                    <flux:icon.rectangle-group class="mb-2 size-5" />
                    <p class="text-sm font-medium">{{ __('Floating') }}</p>
                    <p class="text-xs opacity-70">{{ __('Card chrome') }}</p>
                </button>
                <button
                    type="button"
                    class="rounded-2xl border px-3 py-3 text-start transition"
                    x-on:click="$store.adminTheme.setLayout('attached')"
                    x-bind:class="$store.adminTheme.layout === 'attached'
                        ? 'border-primary bg-primary/10 text-primary'
                        : 'border-zinc-200 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800'"
                >
                    <flux:icon.bars-3-bottom-left class="mb-2 size-5" />
                    <p class="text-sm font-medium">{{ __('Attached') }}</p>
                    <p class="text-xs opacity-70">{{ __('Flush edges') }}</p>
                </button>
            </div>
        </section>

        <section class="space-y-3">
            <p class="text-xs font-semibold uppercase tracking-wider text-zinc-500">{{ __('Navbar') }}</p>
            <div class="grid grid-cols-2 gap-2">
                <button
                    type="button"
                    class="rounded-2xl border px-3 py-3 text-start transition"
                    x-on:click="$store.adminTheme.setNavbarFixed(true)"
                    x-bind:class="$store.adminTheme.navbarFixed
                        ? 'border-primary bg-primary/10 text-primary'
                        : 'border-zinc-200 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800'"
                >
                    <p class="text-sm font-medium">{{ __('Fixed') }}</p>
                    <p class="text-xs opacity-70">{{ __('Sticky on scroll') }}</p>
                </button>
                <button
                    type="button"
                    class="rounded-2xl border px-3 py-3 text-start transition"
                    x-on:click="$store.adminTheme.setNavbarFixed(false)"
                    x-bind:class="!$store.adminTheme.navbarFixed
                        ? 'border-primary bg-primary/10 text-primary'
                        : 'border-zinc-200 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800'"
                >
                    <p class="text-sm font-medium">{{ __('Static') }}</p>
                    <p class="text-xs opacity-70">{{ __('Scrolls away') }}</p>
                </button>
            </div>
        </section>

        <section class="space-y-3">
            <p class="text-xs font-semibold uppercase tracking-wider text-zinc-500">{{ __('Rounded corners') }}</p>
            <div class="grid grid-cols-5 gap-2">
                <template x-for="radius in $store.adminTheme.radii" :key="radius.key">
                    <button
                        type="button"
                        class="flex flex-col items-center gap-2 rounded-2xl border px-2 py-3 transition"
                        x-on:click="$store.adminTheme.setUiRadius(radius.key)"
                        x-bind:class="$store.adminTheme.uiRadius === radius.key
                            ? 'border-primary bg-primary/10 text-primary'
                            : 'border-zinc-200 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800'"
                    >
                        <span
                            class="size-7 border-2 border-current bg-[var(--admin-surface-muted)]"
                            x-bind:style="{
                                borderRadius: ({
                                    none: '0px',
                                    xs: '4px',
                                    sm: '8px',
                                    md: '12px',
                                    xl: '20px',
                                })[radius.key]
                            }"
                        ></span>
                        <span class="text-[11px] font-semibold uppercase tracking-wide" x-text="radius.label"></span>
                    </button>
                </template>
            </div>
        </section>

        <section class="space-y-3">
            <p class="text-xs font-semibold uppercase tracking-wider text-zinc-500">{{ __('Color presets') }}</p>
            <div class="grid gap-2">
                <template x-for="preset in $store.adminTheme.presets" :key="preset.key">
                    <button
                        type="button"
                        class="flex w-full items-center gap-3 rounded-2xl border px-3 py-3 text-start transition"
                        x-on:click="$store.adminTheme.setPreset(preset.key)"
                        x-bind:class="$store.adminTheme.preset === preset.key
                            ? 'border-primary bg-primary/10'
                            : 'border-zinc-200 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800'"
                    >
                        <span class="admin-swatch-pair">
                            <span class="admin-swatch" :style="'background:' + preset.primary"></span>
                            <span class="admin-swatch" :style="'background:' + preset.secondary"></span>
                        </span>
                        <span class="flex-1 text-sm font-medium" x-text="preset.label"></span>
                        <span class="text-primary text-xs" x-show="$store.adminTheme.preset === preset.key">✓</span>
                    </button>
                </template>
            </div>
        </section>

        <div class="mt-auto rounded-2xl bg-[var(--admin-surface-muted)] p-4 text-xs leading-relaxed text-zinc-500 dark:text-zinc-400">
            {{ __('Preferences are saved to your account and sync across sessions on this app.') }}
        </div>
    </div>
</flux:modal>
