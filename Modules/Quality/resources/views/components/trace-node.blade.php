@props(['node'])

<div class="relative ps-4">
    <span class="absolute start-0 top-3 size-2 rounded-full bg-primary"></span>
    <div class="rounded-xl border border-zinc-200 bg-[var(--admin-surface)] px-3 py-2 dark:border-zinc-700">
        <div class="flex flex-wrap items-center gap-2">
            @if (! empty($node['url']))
                <a href="{{ $node['url'] }}" target="_blank" rel="noopener noreferrer" class="text-sm font-medium text-primary hover:underline">
                    {{ $node['title'] }}
                </a>
            @else
                <p class="text-sm font-medium">{{ $node['title'] }}</p>
            @endif
            <span class="text-xs uppercase tracking-wide text-zinc-400">{{ $node['tone'] ?? '' }}</span>
        </div>
        @if (! empty($node['meta']))
            <p class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">{{ $node['meta'] }}</p>
        @endif
    </div>
    @if (! empty($node['children']))
        <div class="mt-2 space-y-2 border-s border-zinc-200 ps-3 dark:border-zinc-700">
            @foreach ($node['children'] as $child)
                <x-quality::trace-node :node="$child" />
            @endforeach
        </div>
    @endif
</div>
