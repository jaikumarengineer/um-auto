<x-admin.theme.sidebar :title="$title ?? null">
    <flux:main>
        <div class="flex flex-col min-h-screen">
            <x-admin.theme.navbar />
            {{ $slot }}
            <x-admin.theme.footer />
        </div>
    </flux:main>
</x-admin.theme.sidebar>
