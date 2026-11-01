@props([
    'activities' => [],
])

@php
    $items = collect($activities);
@endphp

<div {{ $attributes->class('admin-card space-y-3 p-5') }}>
    <p class="text-sm font-semibold">{{ __('Activity') }}</p>

    @forelse ($items as $activity)
        <div wire:key="activity-{{ $activity->id }}" class="flex gap-3">
            <div class="pt-0.5">
                @if ($activity->actor)
                    <flux:avatar
                        size="sm"
                        circle
                        :src="$activity->actor->avatar_url"
                        :name="$activity->actor->full_name"
                        :alt="$activity->actor->full_name"
                    />
                @else
                    <flux:avatar size="sm" circle name="?" />
                @endif
            </div>
            <div class="min-w-0 flex-1 space-y-0.5">
                <p class="text-sm text-zinc-900 dark:text-zinc-100">
                    <span class="font-medium">{{ $activity->actor?->full_name ?: __('System') }}</span>
                    <span class="text-zinc-600 dark:text-zinc-300">{{ $activity->summary() }}</span>
                </p>
                @if (filled($activity->reason))
                    <p class="text-xs text-zinc-500">{{ $activity->reason }}</p>
                @endif
                <p class="text-xs text-zinc-400">{{ $activity->created_at?->format('d M Y H:i') }}</p>
            </div>
        </div>
    @empty
        <p class="text-sm text-zinc-500">{{ __('No activity recorded yet.') }}</p>
    @endforelse
</div>
