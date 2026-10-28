@props([
    'column',
    'label' => null,
    'align' => 'left',
    'sortBy' => null,
    'sortDirection' => 'asc',
])

@php
    $alignClass = match ($align) {
        'center' => 'text-center',
        'right' => 'text-right',
        default => 'text-left',
    };
    $justifyClass = match ($align) {
        'center' => 'justify-center',
        'right' => 'justify-end',
        default => '',
    };
    $isActive = $sortBy === $column;
@endphp

<th {{ $attributes->class([
    'px-4 py-3 text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400',
    $alignClass,
]) }}>
    <button
        type="button"
        wire:click="sort('{{ $column }}')"
        @class([
            'inline-flex items-center gap-1 transition-colors hover:text-primary',
            $justifyClass,
        ])
    >
        <span>{{ $label ?? $slot }}</span>
        @if ($isActive)
            <flux:icon
                :name="$sortDirection === 'asc' ? 'chevron-up' : 'chevron-down'"
                class="size-3.5 text-primary"
            />
        @else
            <flux:icon.chevron-up-down class="size-3.5 opacity-40" />
        @endif
    </button>
</th>
