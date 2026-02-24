<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white antialiased dark:bg-linear-to-b dark:from-neutral-950 dark:to-neutral-900">
        <div class="relative grid h-dvh flex-col items-center justify-center px-8 sm:px-0 lg:max-w-none lg:grid-cols-2 lg:px-0">

            {{-- Left panel: random workspace background image with overlay, logo + quote --}}
            @php
                $bgImages = [
                    'https://images.unsplash.com/photo-1497366216548-37526070297c?w=1280&q=80&auto=format&fit=crop', // modern open office workspace
                    'https://images.unsplash.com/photo-1524758631624-e2822e304c36?w=1280&q=80&auto=format&fit=crop', // minimal white desk setup
                    'https://images.unsplash.com/photo-1541746972996-4e0b0f43e02a?w=1280&q=80&auto=format&fit=crop', // cozy dark desk with lamp
                    'https://images.unsplash.com/photo-1593642632559-0c6d3fc62b89?w=1280&q=80&auto=format&fit=crop', // dark laptop workspace
                    'https://images.unsplash.com/photo-1504384308090-c894fdcc538d?w=1280&q=80&auto=format&fit=crop', // coworking open plan office
                    'https://images.unsplash.com/photo-1486312338219-ce68d2c6f44d?w=1280&q=80&auto=format&fit=crop', // person typing on macbook
                    'https://images.unsplash.com/photo-1519389950473-47ba0277781c?w=1280&q=80&auto=format&fit=crop', // team working with laptops
                    'https://images.unsplash.com/photo-1507238691740-187a5b1d37b8?w=1280&q=80&auto=format&fit=crop', // clean dual monitor setup
                ];
                $bgImage = $bgImages[array_rand($bgImages)];
            @endphp
            <div class="relative hidden h-full flex-col p-10 text-white lg:flex overflow-hidden">

                {{-- Randomly selected workspace image --}}
                <img
                    src="{{ $bgImage }}"
                    alt=""
                    class="absolute inset-0 h-full w-full object-cover transition-all duration-700"
                />

                {{-- Dark gradient overlay for readability --}}
                <div class="absolute inset-0 bg-gradient-to-br from-neutral-950/90 via-neutral-900/75 to-indigo-950/80"></div>

                {{-- Decorative blurred accent circles --}}
                <div class="absolute -top-24 -left-24 h-96 w-96 rounded-full bg-indigo-600/20 blur-3xl"></div>
                <div class="absolute -bottom-32 -right-16 h-80 w-80 rounded-full bg-violet-600/20 blur-3xl"></div>

                {{-- Logo --}}
                <a href="{{ url('/') }}" class="relative z-20 flex items-center gap-2 text-lg font-semibold tracking-tight" wire:navigate>
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-white/10 backdrop-blur-sm ring-1 ring-white/20">
                        <x-app-logo-icon class="h-6 fill-current text-white" />
                    </span>
                    {{ config('app.name', 'Laravel') }}
                </a>

                {{-- Feature highlights --}}
                <div class="relative z-20 mt-12 flex flex-col gap-4">
                    @foreach ([
                        ['icon' => '⚡', 'title' => 'Lightning Fast',    'text'  => 'Built on Laravel & Livewire for real-time reactivity.'],
                        ['icon' => '🔒', 'title' => 'Secure by Default', 'text'  => 'Role-based access control with full audit trails.'],
                        ['icon' => '📊', 'title' => 'Rich Analytics',    'text'  => 'Beautiful dashboards and reporting out of the box.'],
                    ] as $feature)
                        <div class="flex items-start gap-3 rounded-xl bg-white/5 px-4 py-3 backdrop-blur-sm ring-1 ring-white/10">
                            <span class="mt-0.5 text-xl leading-none">{{ $feature['icon'] }}</span>
                            <div>
                                <p class="text-sm font-semibold text-white">{{ $feature['title'] }}</p>
                                <p class="text-xs text-white/60 leading-relaxed">{{ $feature['text'] }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Random inspiring quote at the bottom --}}
                @php
                    [$message, $author] = str(Illuminate\Foundation\Inspiring::quotes()->random())->explode('-');
                @endphp
                <div class="relative z-20 mt-auto">
                    <div class="mb-4 h-px bg-gradient-to-r from-transparent via-white/20 to-transparent"></div>
                    <blockquote class="space-y-1">
                        <p class="text-sm italic text-white/70 leading-relaxed">&ldquo;{{ trim($message) }}&rdquo;</p>
                        <footer class="text-xs font-medium text-white/40">&mdash; {{ trim($author) }}</footer>
                    </blockquote>
                </div>
            </div>

            {{-- Right panel: auth form --}}
            <div class="w-full lg:p-8">
                <div class="mx-auto flex w-full flex-col justify-center space-y-6 sm:w-[350px]">

                    {{-- Logo shown only on mobile (hidden on large screens where left panel shows it) --}}
                    <a href="{{ url('/') }}" class="z-20 flex flex-col items-center gap-2 font-medium lg:hidden" wire:navigate>
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
