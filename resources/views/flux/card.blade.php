@props([
    'header' => null,
    'footer' => null,
    'image' => null,
    'actions' => [],
    'size' => 'full', // new size attribute: sm, md, lg,
    'padding'=>'p-6',
    'shadow' => 'none',
])

@php
    $shadowClasses = [
        'sm' => 'shadow-sm',
        'md' => 'shadow-md',
        'lg' => 'shadow-lg',
        'xl' => 'shadow-xl',
        '2xl' => 'shadow-2xl',
        'none' => '',
    ];

    $sizeClasses = [
        'sm' => 'max-w-sm',
        'md' => 'max-w-md',
        'lg' => 'max-w-lg',
        'xl' => 'max-w-xl',
        '2xl' => 'max-w-2xl',
        '3xl' => 'max-w-3xl',
        '4xl' => 'max-w-4xl',
        '5xl' => 'max-w-5xl',
        'full' => 'w-full',
    ];
@endphp

<div {{ $attributes->merge([
    'class' =>
        ($sizeClasses[$size] ?? $sizeClasses['md']) .
        ' rounded-xl bg-white dark:bg-zinc-700 border dark:border-zinc-600 ' .
        ($shadowClasses[$shadow] ?? $shadowClasses['lg']) . ' ' .
        $padding
    ]) }}>
    @if($image)
        <img src="{{ $image }}" alt="" class="rounded-t-xl mb-4 w-full object-cover" />
    @endif

    @if($header)
        <div class="mb-2 font-bold text-lg">{{ $header }}</div>
    @endif

    <div>
        {{ $slot }}
    </div>

    @if($footer || $actions)
        <div class="mt-4 flex justify-between items-center">
            <div>{{ $footer }}</div>
            <div class="space-x-2">
                @foreach($actions as $action)
                    <button type="button" class="inline-flex items-center px-4 py-2 rounded-md bg-blue-600 text-white hover:bg-blue-700 text-sm font-medium transition">
                        {{ $action }}
                    </button>
                @endforeach
            </div>
        </div>
    @endif
</div>
