@props([
    'users' => [],
    'title' => null,
])

@php
    $people = collect($users)
        ->filter(fn ($user) => $user instanceof \App\Models\User)
        ->unique('id')
        ->values();
    $count = $people->count();
    $heading = $title ?: ($count === 1 ? __('Person') : __('People (:count)', ['count' => $count]));
@endphp

@if ($count === 0)
    <span {{ $attributes->class('text-sm text-zinc-500') }}>—</span>
@else
    <div {{ $attributes->class('inline-flex') }}>
    <flux:dropdown position="bottom" align="start">
        <button
            type="button"
            class="group inline-flex max-w-full items-center gap-1.5 rounded-lg p-0.5 text-left transition hover:bg-zinc-100 dark:hover:bg-zinc-800"
            title="{{ $people->map(fn ($user) => $user->full_name)->join(', ') }}"
        >
            <flux:avatar.group>
                @foreach ($people->take(3) as $user)
                    <flux:avatar
                        size="sm"
                        circle
                        :src="$user->avatar_url"
                        :name="$user->full_name"
                        :alt="$user->full_name"
                    />
                @endforeach
            </flux:avatar.group>
            @if ($count > 3)
                <span class="text-xs font-medium text-zinc-500">+{{ $count - 3 }}</span>
            @endif
        </button>

        <flux:menu class="min-w-72 !p-0">
            <div class="flex items-center justify-between border-b border-zinc-200 px-3 py-2.5 dark:border-zinc-700">
                <p class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ $heading }}</p>
            </div>
            <ul class="max-h-72 space-y-1 overflow-y-auto p-2">
                @foreach ($people as $user)
                    <li class="flex items-center gap-3 rounded-lg px-2 py-2">
                        <flux:avatar
                            size="sm"
                            circle
                            :src="$user->avatar_url"
                            :name="$user->full_name"
                            :alt="$user->full_name"
                        />
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $user->full_name }}</p>
                            <p class="truncate text-xs text-zinc-500">{{ $user->email }}</p>
                        </div>
                        <span class="shrink-0 rounded-md bg-zinc-900 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-white dark:bg-zinc-100 dark:text-zinc-900">
                            {{ $user->roleLabel() }}
                        </span>
                    </li>
                @endforeach
            </ul>
        </flux:menu>
    </flux:dropdown>
    </div>
@endif
