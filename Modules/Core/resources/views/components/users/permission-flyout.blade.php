@props([
    'title' => '',
    'draftModel' => 'draftPermissions',
    'openModel' => 'showPermissionsFlyout',
])

@php
    use App\Support\PermissionCatalog;

    $modules = PermissionCatalog::modules();
    $allNames = PermissionCatalog::allPermissionNames();
    $draft = collect($this->{$draftModel} ?? []);
    $selectedCount = $draft->intersect($allNames)->count();
    $totalCount = count($allNames);
@endphp

<flux:modal
    wire:model="{{ $openModel }}"
    variant="flyout"
    class="admin-permission-flyout md:min-w-[26rem] lg:min-w-[30rem] !rounded-s-3xl !p-0 dark:!bg-[var(--admin-surface)]"
>
    <div class="flex h-full flex-col">
        <div class="space-y-2.5 border-b border-zinc-200 px-6 pb-6 pt-10 pe-14 sm:px-8 dark:border-zinc-700">
            <flux:heading size="lg">{{ $title }}</flux:heading>
            <flux:subheading>
                {{ __(':selected of :total permissions selected', [
                    'selected' => $selectedCount,
                    'total' => $totalCount,
                ]) }}
            </flux:subheading>
        </div>

        <div class="flex items-center justify-between gap-3 px-6 py-5 sm:px-8">
            <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-zinc-400">
                {{ __('By category') }}
            </p>
            <div class="flex items-center gap-4 text-sm">
                <button
                    type="button"
                    class="font-medium text-[var(--admin-primary)] hover:underline"
                    wire:click="selectAllDraftPermissions"
                >
                    {{ __('Select all') }}
                </button>
                <button
                    type="button"
                    class="font-medium text-zinc-500 hover:underline"
                    wire:click="clearDraftPermissions"
                >
                    {{ __('Clear') }}
                </button>
            </div>
        </div>

        <div class="flex-1 space-y-5 overflow-y-auto px-6 pb-6 sm:px-8">
            @foreach ($modules as $module => $moduleLabel)
                @php
                    $rows = PermissionCatalog::modulePermissionRows($module);
                    $rowNames = collect($rows)->pluck('name');
                    $moduleSelected = $rowNames->filter(fn ($name) => $draft->contains($name))->count();
                    $moduleTotal = $rowNames->count();
                    $moduleAllOn = $moduleSelected === $moduleTotal && $moduleTotal > 0;
                @endphp

                <details class="overflow-hidden rounded-xl border border-zinc-200 bg-white open:shadow-sm dark:border-zinc-700 dark:bg-zinc-900" open wire:key="flyout-module-{{ $module }}">
                    <summary class="flex cursor-pointer list-none items-center gap-3 px-5 py-4 [&::-webkit-details-marker]:hidden">
                        <span class="inline-flex" x-on:click.stop>
                            <input
                                type="checkbox"
                                class="size-4 rounded border-zinc-300 text-[var(--admin-primary)] focus:ring-[var(--admin-primary)]"
                                @checked($moduleAllOn)
                                wire:click='toggleDraftModule(@js($module))'
                            >
                        </span>
                        <span class="min-w-0 flex-1 text-sm font-semibold text-zinc-800 dark:text-zinc-100">
                            {{ __($moduleLabel) }}
                        </span>
                        <span class="rounded-full bg-zinc-100 px-2.5 py-0.5 text-[11px] font-medium tabular-nums text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                            {{ $moduleSelected }}/{{ $moduleTotal }}
                        </span>
                        <flux:icon name="chevron-down" class="size-4 text-zinc-400 transition-transform [[open]_&]:rotate-180" />
                    </summary>

                    <div class="divide-y divide-zinc-100 border-t border-zinc-100 dark:divide-zinc-800 dark:border-zinc-800">
                        @foreach ($rows as $row)
                            <label
                                class="flex cursor-pointer items-center gap-3 px-5 py-4 hover:bg-zinc-50 dark:hover:bg-zinc-800/50"
                                wire:key="flyout-perm-{{ $row['name'] }}"
                                wire:click='toggleDraftPermission(@js($row['name']))'
                            >
                                <input
                                    type="checkbox"
                                    @checked($draft->contains($row['name']))
                                    tabindex="-1"
                                    class="pointer-events-none size-4 rounded border-zinc-300 text-[var(--admin-primary)] focus:ring-[var(--admin-primary)]"
                                >
                                <span class="min-w-0 flex-1 text-sm text-zinc-800 dark:text-zinc-100">
                                    {{ __($row['label']) }}
                                </span>
                                <code class="shrink-0 text-[11px] text-zinc-400">{{ $row['name'] }}</code>
                            </label>
                        @endforeach
                    </div>
                </details>
            @endforeach
        </div>

        <div class="flex items-center justify-between gap-4 border-t border-zinc-200 bg-[var(--admin-surface-muted)] px-6 py-6 sm:px-8 dark:border-zinc-700">
            <p class="text-sm text-zinc-500">
                {{ __(':count selected', ['count' => $selectedCount]) }}
            </p>
            <div class="flex items-center gap-3">
                <flux:button type="button" variant="ghost" wire:click="closePermissionsFlyout">
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button type="button" variant="primary" wire:click="savePermissionsFlyout">
                    {{ __('Save Changes') }}
                </flux:button>
            </div>
        </div>
    </div>
</flux:modal>
