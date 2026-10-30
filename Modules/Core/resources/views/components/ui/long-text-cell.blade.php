@props([
    'value' => null,
    'empty' => '—',
])

@php
    $text = is_string($value) ? trim($value) : (filled($value) ? (string) $value : '');
@endphp

<td {{ $attributes->class('admin-long-text-cell px-4 py-3 text-sm text-zinc-700 dark:text-zinc-200') }}>
    @if ($text === '')
        <span class="text-zinc-400">{{ $empty }}</span>
    @else
        <div class="admin-long-text">{{ $text }}</div>
    @endif
</td>
