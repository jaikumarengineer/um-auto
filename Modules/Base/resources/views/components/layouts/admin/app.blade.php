<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">

<head>
    @include('partials.head')
    @fluxAppearance
</head>

<body class="min-h-screen bg-white dark:bg-zinc-800">

    <x-base::admin.theme.sidebar />
    <x-base::admin.theme.navbar />
    <flux:main>
        <div class="flex flex-col min-h-screen gap-6">
            <div class="flex-1">
                {{ $slot }}
            </div>
            <x-base::admin.theme.footer />
        </div>
    </flux:main>

    @fluxScripts
</body>

</html>
