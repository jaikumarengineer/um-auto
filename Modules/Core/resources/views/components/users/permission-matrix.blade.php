@props([
    'model' => 'grantedPermissions',
    'showCopyFromRole' => false,
    'copyModel' => 'copyFromRole',
    'roleOptions' => [],
])

@php
    use App\Support\PermissionCatalog;

    $modules = PermissionCatalog::modules();
    $actions = PermissionCatalog::actions();
    $actionLabels = PermissionCatalog::actionLabels();
    $groups = PermissionCatalog::groups();
    $granted = collect($this->{$model} ?? []);
@endphp

<div class="space-y-4" {{ $attributes }}>
    <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
        <div class="flex flex-wrap gap-2">
            <flux:button type="button" size="sm" variant="ghost" wire:click="enableAllPermissions">
                {{ __('Enable All') }}
            </flux:button>
            <flux:button type="button" size="sm" variant="ghost" wire:click="disableAllPermissions">
                {{ __('Disable All') }}
            </flux:button>
            <flux:button type="button" size="sm" variant="ghost" wire:click="viewOnlyPermissions">
                {{ __('View Only') }}
            </flux:button>
        </div>

        @if ($showCopyFromRole)
            <div class="flex min-w-[14rem] flex-1 items-end gap-2 sm:max-w-xs sm:flex-none">
                <div class="min-w-0 flex-1">
                    <flux:select wire:model.live="{{ $copyModel }}" :label="__('Copy permissions from role')">
                        <flux:select.option value="">{{ __('Select role…') }}</flux:select.option>
                        @foreach ($roleOptions as $value => $label)
                            <flux:select.option :value="$value">{{ $label }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
            </div>
        @endif
    </div>

    <div class="space-y-3">
        @foreach ($groups as $groupKey => $group)
            @php
                $groupModules = collect($group['modules'])->filter(fn ($m) => isset($modules[$m]));
                $groupPermissionNames = $groupModules->flatMap(
                    fn ($m) => collect($actions)->map(fn ($a) => PermissionCatalog::name($m, $a))
                );
                $enabledCount = $groupPermissionNames->filter(fn ($name) => $granted->contains($name))->count();
                $totalCount = $groupPermissionNames->count();
            @endphp

            <details class="admin-card overflow-hidden" open wire:key="perm-group-{{ $groupKey }}">
                <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-4 py-3 [&::-webkit-details-marker]:hidden">
                    <div class="flex items-center gap-2">
                        <flux:icon name="chevron-right" class="size-4 text-zinc-400 transition-transform [[open]_&]:rotate-90" />
                        <span class="text-sm font-semibold text-zinc-800 dark:text-zinc-100">{{ __($group['label']) }}</span>
                    </div>
                    <span class="rounded-full bg-zinc-100 px-2.5 py-0.5 text-xs font-medium text-zinc-600 dark:bg-zinc-700 dark:text-zinc-300">
                        {{ __(':enabled of :total enabled', ['enabled' => $enabledCount, 'total' => $totalCount]) }}
                    </span>
                </summary>

                <div class="overflow-x-auto border-t border-zinc-200 dark:border-zinc-700">
                    <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                        <thead class="bg-[var(--admin-surface-muted)]">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-zinc-500">{{ __('Module') }}</th>
                                @foreach ($actionLabels as $action => $label)
                                    <th class="px-2 py-2 text-center text-xs font-semibold text-zinc-500">
                                        <button
                                            type="button"
                                            class="underline-offset-2 hover:underline"
                                            wire:click="toggleColumnPermissions('{{ $groupKey }}', '{{ $action }}')"
                                        >
                                            {{ __($label) }}
                                        </button>
                                    </th>
                                @endforeach
                                <th class="px-2 py-2 text-center text-xs font-semibold text-zinc-500">{{ __('All') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                            @foreach ($groupModules as $module)
                                @php($rowNames = collect($actions)->map(fn ($a) => PermissionCatalog::name($module, $a)))
                                <tr wire:key="perm-row-{{ $module }}">
                                    <td class="px-4 py-2 font-medium text-zinc-700 dark:text-zinc-200">
                                        <button
                                            type="button"
                                            class="text-left underline-offset-2 hover:underline"
                                            wire:click="toggleRowPermissions('{{ $module }}')"
                                        >
                                            {{ __($modules[$module]) }}
                                        </button>
                                    </td>
                                    @foreach ($actions as $action)
                                        @php($permission = PermissionCatalog::name($module, $action))
                                        <td class="px-2 py-2 text-center">
                                            <input
                                                type="checkbox"
                                                @checked($granted->contains($permission))
                                                wire:click='toggleGrantedPermission(@js($permission))'
                                                class="size-4 rounded border-zinc-300 text-[var(--admin-primary)] focus:ring-[var(--admin-primary)]"
                                            >
                                        </td>
                                    @endforeach
                                    <td class="px-2 py-2 text-center">
                                        <input
                                            type="checkbox"
                                            @checked($rowNames->every(fn ($name) => $granted->contains($name)))
                                            wire:click="toggleRowPermissions('{{ $module }}')"
                                            class="size-4 rounded border-zinc-300 text-[var(--admin-primary)] focus:ring-[var(--admin-primary)]"
                                        >
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </details>
        @endforeach
    </div>
</div>
