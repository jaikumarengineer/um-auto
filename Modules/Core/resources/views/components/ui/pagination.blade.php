@props([
    'paginator',
])

@if ($paginator->hasPages())
    <div {{ $attributes->class(['mt-4']) }}>
        {{ $paginator->links() }}
    </div>
@endif
