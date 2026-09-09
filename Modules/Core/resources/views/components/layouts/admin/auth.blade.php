<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
        <style>
            @keyframes admin-auth-float {
                0%, 100% { transform: translate3d(0, 0, 0) scale(1); }
                50% { transform: translate3d(0, -24px, 0) scale(1.06); }
            }
            @keyframes admin-auth-fade-up {
                from { opacity: 0; transform: translateY(10px); }
                to { opacity: 1; transform: translateY(0); }
            }

            /* Base panel surface */
            .admin-auth-panel {
                background: linear-gradient(165deg, #f8fafc 0%, #eef6f5 45%, #edf0fc 100%);
            }
            .dark .admin-auth-panel {
                background: #09090b;
            }

            /* Radial brand glows */
            .admin-auth-glow-1 {
                background: radial-gradient(ellipse 75% 60% at 15% 0%, color-mix(in srgb, var(--admin-primary) 20%, transparent), transparent);
            }
            .dark .admin-auth-glow-1 {
                background: radial-gradient(ellipse 75% 60% at 15% 0%, color-mix(in srgb, var(--admin-primary) 32%, transparent), transparent);
            }
            .admin-auth-glow-2 {
                background: radial-gradient(ellipse 60% 50% at 100% 100%, rgb(99 102 241 / 0.12), transparent);
            }
            .dark .admin-auth-glow-2 {
                background: radial-gradient(ellipse 60% 50% at 100% 100%, rgb(99 102 241 / 0.22), transparent);
            }

            /* Floating accent orbs */
            .admin-auth-orb {
                position: absolute;
                border-radius: 9999px;
                filter: blur(80px);
                opacity: 0.16;
                pointer-events: none;
            }
            .dark .admin-auth-orb {
                opacity: 0.45;
            }
            .admin-auth-orb-1 {
                top: -7rem;
                left: -6rem;
                height: 24rem;
                width: 24rem;
                background: var(--admin-primary, #0d9488);
                animation: admin-auth-float 11s ease-in-out infinite;
            }
            .admin-auth-orb-2 {
                bottom: -9rem;
                right: -5rem;
                height: 22rem;
                width: 22rem;
                background: #6366f1;
                animation: admin-auth-float 13s ease-in-out infinite reverse;
            }

            /* Faint grid */
            .admin-auth-grid {
                background-image:
                    linear-gradient(to right, rgb(15 23 42 / 0.05) 1px, transparent 1px),
                    linear-gradient(to bottom, rgb(15 23 42 / 0.05) 1px, transparent 1px);
                background-size: 42px 42px;
                mask-image: radial-gradient(ellipse 80% 60% at 30% 20%, black 40%, transparent 90%);
            }
            .dark .admin-auth-grid {
                background-image:
                    linear-gradient(to right, rgb(255 255 255 / 0.06) 1px, transparent 1px),
                    linear-gradient(to bottom, rgb(255 255 255 / 0.06) 1px, transparent 1px);
            }

            /* Bottom fade so orbs/grid don't collide with the quote */
            .admin-auth-fade {
                background: linear-gradient(to top, #f8fafc, transparent);
            }
            .dark .admin-auth-fade {
                background: linear-gradient(to top, #09090b, transparent);
            }

            /* Glass card + tiles (dashboard preview, feature tiles) */
            .admin-auth-card {
                border: 1px solid rgb(15 23 42 / 0.08);
                background: rgb(255 255 255 / 0.72);
                box-shadow: 0 1px 2px rgb(15 23 42 / 0.04), 0 24px 48px -24px rgb(15 23 42 / 0.18);
            }
            .dark .admin-auth-card {
                border-color: rgb(255 255 255 / 0.1);
                background: rgb(255 255 255 / 0.04);
                box-shadow: 0 24px 48px -24px rgb(0 0 0 / 0.6);
            }
            .admin-auth-tile {
                border: 1px solid rgb(15 23 42 / 0.07);
                background: rgb(15 23 42 / 0.025);
            }
            .dark .admin-auth-tile {
                border-color: rgb(255 255 255 / 0.1);
                background: rgb(255 255 255 / 0.03);
            }

            /* Brand-tinted icon badge (logo + feature icons) */
            .admin-auth-icon-badge {
                background: color-mix(in srgb, var(--admin-primary) 12%, white);
                border: 1px solid color-mix(in srgb, var(--admin-primary) 22%, transparent);
                color: var(--admin-primary);
            }
            .dark .admin-auth-icon-badge {
                background: rgb(255 255 255 / 0.1);
                border-color: rgb(255 255 255 / 0.15);
                color: #ffffff;
            }

            /* Status + appearance pill buttons */
            .admin-auth-pill {
                border: 1px solid rgb(15 23 42 / 0.08);
                background: rgb(255 255 255 / 0.6);
                color: rgb(15 23 42 / 0.6);
            }
            .dark .admin-auth-pill {
                border-color: rgb(255 255 255 / 0.12);
                background: rgb(255 255 255 / 0.06);
                color: rgb(255 255 255 / 0.7);
            }

            .admin-auth-divider {
                background: linear-gradient(to right, transparent, rgb(15 23 42 / 0.12), transparent);
            }
            .dark .admin-auth-divider {
                background: linear-gradient(to right, transparent, rgb(255 255 255 / 0.15), transparent);
            }

            .admin-auth-enter {
                animation: admin-auth-fade-up 0.6s ease-out both;
            }
            .admin-auth-enter-1 { animation-delay: 0.05s; }
            .admin-auth-enter-2 { animation-delay: 0.15s; }
            .admin-auth-enter-3 { animation-delay: 0.25s; }
            .admin-auth-enter-4 { animation-delay: 0.35s; }
            @media (prefers-reduced-motion: reduce) {
                .admin-auth-orb { animation: none; }
                .admin-auth-enter { animation: none; }
            }
        </style>
    </head>
    <body class="min-h-screen bg-white antialiased dark:bg-linear-to-b dark:from-neutral-950 dark:to-neutral-900">
        <div class="relative grid h-dvh flex-col items-center justify-center px-8 sm:px-0 lg:max-w-none lg:grid-cols-2 lg:px-0">

            {{-- Left panel: modern gradient-mesh brand showcase (adapts to light/dark) --}}
            <div class="admin-auth-panel relative hidden h-full flex-col justify-between overflow-hidden p-10 text-zinc-900 dark:text-white lg:flex">

                {{-- Layered background: radial glow + grid + floating orbs --}}
                <div class="pointer-events-none absolute inset-0">
                    <div class="admin-auth-glow-1 absolute inset-0"></div>
                    <div class="admin-auth-glow-2 absolute inset-0"></div>
                    <div class="admin-auth-grid absolute inset-0"></div>
                    <div class="admin-auth-orb admin-auth-orb-1"></div>
                    <div class="admin-auth-orb admin-auth-orb-2"></div>
                    <div class="admin-auth-fade absolute inset-0"></div>
                </div>

                {{-- Top: logo + status pill + appearance toggle --}}
                <div class="admin-auth-enter admin-auth-enter-1 relative z-10 flex items-center justify-between gap-3">
                    <a href="{{ url('/') }}" class="flex items-center gap-2.5 text-lg font-semibold tracking-tight" wire:navigate>
                        <span class="admin-auth-icon-badge flex h-10 w-10 items-center justify-center rounded-xl backdrop-blur-sm">
                            <x-app-logo-icon class="h-5 fill-current" />
                        </span>
                        {{ config('app.name', 'Laravel') }}
                    </a>
                    <div class="flex items-center gap-2">
                        <span class="admin-auth-pill hidden items-center gap-1.5 rounded-full px-3 py-1 text-[11px] font-medium backdrop-blur-sm sm:inline-flex">
                            <span class="size-1.5 rounded-full bg-emerald-500 dark:bg-emerald-400"></span>
                            {{ __('All systems operational') }}
                        </span>
                        <div x-data>
                            <button
                                type="button"
                                class="admin-auth-pill flex h-8 w-8 items-center justify-center rounded-full backdrop-blur-sm"
                                x-on:click="$flux.appearance = ($flux.appearance === 'dark' ? 'light' : 'dark')"
                                aria-label="{{ __('Toggle appearance') }}"
                            >
                                <flux:icon.moon variant="outline" class="size-4" x-show="$flux.appearance !== 'dark'" x-cloak />
                                <flux:icon.sun variant="outline" class="size-4" x-show="$flux.appearance === 'dark'" x-cloak />
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Middle: headline + mock dashboard preview + features --}}
                <div class="relative z-10 flex flex-1 flex-col justify-center gap-8 py-10">
                    <div class="admin-auth-enter admin-auth-enter-2 max-w-md">
                        <h1 class="text-3xl font-semibold leading-tight tracking-tight">
                            {{ __('Run your workspace') }}<br />{{ __('with total clarity.') }}
                        </h1>
                        <p class="mt-3 text-sm leading-relaxed text-zinc-600 dark:text-white/60">
                            {{ __('One dashboard for users, settings, and content — fast, secure, and built to scale with your team.') }}
                        </p>
                    </div>

                    {{-- Mock dashboard preview card --}}
                    <div class="admin-auth-enter admin-auth-enter-3 admin-auth-card relative rounded-2xl p-4 backdrop-blur-md">
                        <div class="flex items-center gap-1.5 pb-3">
                            <span class="size-2.5 rounded-full bg-red-400/70"></span>
                            <span class="size-2.5 rounded-full bg-amber-400/70"></span>
                            <span class="size-2.5 rounded-full bg-emerald-400/70"></span>
                        </div>
                        <div class="grid grid-cols-3 gap-3">
                            @foreach ([
                                ['label' => __('Active Users'), 'value' => '12.4k', 'trend' => '+18%'],
                                ['label' => __('Revenue'), 'value' => '$48.2k', 'trend' => '+9%'],
                                ['label' => __('Uptime'), 'value' => '99.9%', 'trend' => __('stable')],
                            ] as $stat)
                                <div class="admin-auth-tile rounded-xl p-3">
                                    <p class="text-[10px] uppercase tracking-wide text-zinc-500 dark:text-white/40">{{ $stat['label'] }}</p>
                                    <p class="mt-1 text-lg font-semibold">{{ $stat['value'] }}</p>
                                    <p class="mt-0.5 text-[11px] font-medium text-emerald-600 dark:text-emerald-400">{{ $stat['trend'] }}</p>
                                </div>
                            @endforeach
                        </div>
                        <div class="admin-auth-tile mt-3 flex h-16 items-end gap-1.5 rounded-xl p-3">
                            @foreach ([35, 55, 45, 70, 60, 90, 65, 80, 50, 85, 72, 100] as $bar)
                                <span
                                    class="flex-1 rounded-sm opacity-80"
                                    style="height: {{ $bar }}%; background: linear-gradient(to top, color-mix(in srgb, var(--admin-primary) 55%, transparent), var(--admin-primary));"
                                ></span>
                            @endforeach
                        </div>
                    </div>

                    {{-- Feature highlights --}}
                    <div class="admin-auth-enter admin-auth-enter-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
                        @foreach ([
                            ['icon' => 'bolt', 'title' => __('Lightning fast'), 'text' => __('Real-time reactivity with Livewire.')],
                            ['icon' => 'shield-check', 'title' => __('Secure by default'), 'text' => __('Role-based access, full audit trail.')],
                            ['icon' => 'chart-bar', 'title' => __('Rich analytics'), 'text' => __('Dashboards & reporting, ready to go.')],
                        ] as $feature)
                            <div class="admin-auth-tile rounded-xl p-3.5 backdrop-blur-sm">
                                <span class="admin-auth-icon-badge flex size-8 items-center justify-center rounded-lg">
                                    <flux:icon :icon="$feature['icon']" variant="outline" class="size-4" />
                                </span>
                                <p class="mt-2.5 text-sm font-semibold">{{ $feature['title'] }}</p>
                                <p class="mt-0.5 text-xs leading-relaxed text-zinc-600 dark:text-white/50">{{ $feature['text'] }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Bottom: inspiring quote --}}
                @php
                    [$message, $author] = str(Illuminate\Foundation\Inspiring::quotes()->random())->explode('-');
                @endphp
                <div class="relative z-10">
                    <div class="admin-auth-divider mb-4 h-px"></div>
                    <blockquote class="flex items-start gap-2.5">
                        <flux:icon.chat-bubble-left-right variant="outline" class="mt-0.5 size-4 shrink-0 text-zinc-400 dark:text-white/30" />
                        <div>
                            <p class="text-sm italic leading-relaxed text-zinc-600 dark:text-white/70">&ldquo;{{ trim($message) }}&rdquo;</p>
                            <footer class="mt-1 text-xs font-medium text-zinc-400 dark:text-white/40">&mdash; {{ trim($author) }}</footer>
                        </div>
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
