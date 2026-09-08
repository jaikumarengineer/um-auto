<x-core::layouts.app.sidebar :title="$title ?? null">
    <flux:main class="admin-main-surface">
        {{ $slot }}
    </flux:main>
</x-core::layouts.app.sidebar>
