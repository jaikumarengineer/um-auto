<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">

<head>
    @include('partials.head')
    @fluxAppearance
</head>

<body class="min-h-screen bg-white dark:bg-zinc-800">

    <x-admin.theme.sidebar :title="$title ?? null" />

    <flux:main>
        <div class="flex flex-col min-h-screen">
            <x-admin.theme.navbar />
            {{ $slot }}
            <x-admin.theme.footer />
        </div>
    </flux:main>

    @fluxScripts
</body>

</html>
