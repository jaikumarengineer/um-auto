<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white antialiased dark:bg-linear-to-b dark:from-neutral-950 dark:to-neutral-900">
        <div class="relative grid h-dvh flex-col items-center justify-center px-8 sm:px-0 lg:max-w-none lg:grid-cols-2 lg:px-0">
            <div class="bg-muted relative hidden h-full flex-col text-white lg:flex dark:border-e dark:border-neutral-800 overflow-hidden"
                x-data="{
                    images: [
                        'https://images.unsplash.com/photo-1590069261209-f8e9b8642343?ixlib=rb-4.0.3&ixid=MnwxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8&auto=format&fit=crop&w=1376&q=80',
                        'https://images.unsplash.com/photo-1579546929518-9e396f3cc809?ixlib=rb-4.0.3&ixid=MnwxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8&auto=format&fit=crop&w=1470&q=80',
                        'https://images.unsplash.com/photo-1621504450168-38f647319936?ixlib=rb-4.0.3&ixid=MnwxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8&auto=format&fit=crop&w=1470&q=80',
                    ],
                    active: 0,
                    init() {
                        setInterval(() => {
                            this.active = (this.active + 1) % this.images.length;
                        }, 5000);
                    }
                }"
            >
                <div class="absolute inset-0 bg-neutral-900">
                    <template x-for="(image, index) in images" :key="index">
                        <div
                            x-show="active === index"
                            x-transition:enter="transition ease-in-out duration-1000"
                            x-transition:enter-start="opacity-0"
                            x-transition:enter-end="opacity-100"
                            x-transition:leave="transition ease-in-out duration-1000"
                            x-transition:leave-start="opacity-100"
                            x-transition:leave-end="opacity-0"
                            class="absolute inset-0 bg-cover bg-center"
                            :style="`background-image: url('${image}')`"
                        >
                            <div class="absolute inset-0 bg-black/40"></div>
                        </div>
                    </template>
                </div>

                <div class="relative z-20 flex h-full flex-col p-10">
                    <a href="{{ route('home') }}" class="flex items-center text-lg font-medium" wire:navigate>
                        <span class="flex h-10 w-10 items-center justify-center rounded-md">
                            <x-app-logo-icon class="me-2 h-7 fill-current text-white" />
                        </span>
                        {{ config('app.name', 'Laravel') }}
                    </a>

                    @php
                        [$message, $author] = str(Illuminate\Foundation\Inspiring::quotes()->random())->explode('-');
                    @endphp

                    <div class="mt-auto">
                        <blockquote class="space-y-2">
                            <flux:heading size="lg" class="text-white">&ldquo;{{ trim($message) }}&rdquo;</flux:heading>
                            <footer><flux:heading class="text-zinc-200">{{ trim($author) }}</flux:heading></footer>
                        </blockquote>
                    </div>
                </div>
            </div>
            <div class="w-full lg:p-8">
                <div class="mx-auto flex w-full flex-col justify-center space-y-6 sm:w-[350px]">
                    <a href="{{ route('home') }}" class="z-20 flex flex-col items-center gap-2 font-medium lg:hidden" wire:navigate>
                        <span class="flex h-9 w-9 items-center justify-center rounded-md">
                            <x-app-logo-icon class="size-9 fill-current text-black dark:text-white" />
                        </span>

                        <span class="sr-only">{{ config('app.name', 'Laravel') }}</span>
                    </a>
                    {{ $slot }}
                </div>
            </div>
        </div>
        @fluxScripts
    </body>
</html>
