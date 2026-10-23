<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
        <style>
            @keyframes admin-auth-float {
                0%, 100% { transform: perspective(1400px) rotateY(-12deg) rotateX(4deg) rotate(-2deg) translate3d(0, 8px, 0); }
                50% { transform: perspective(1400px) rotateY(-12deg) rotateX(4deg) rotate(-2deg) translate3d(0, -10px, 0); }
            }
            @keyframes admin-auth-float-card {
                0%, 100% { transform: translate3d(0, 0, 0); }
                50% { transform: translate3d(0, -12px, 0); }
            }
            @keyframes admin-auth-fade-up {
                from { opacity: 0; transform: translateY(14px); }
                to { opacity: 1; transform: translateY(0); }
            }
            @keyframes admin-auth-pulse-soft {
                0%, 100% { opacity: 0.35; }
                50% { opacity: 0.55; }
            }

            .admin-auth-brand {
                display: none;
                background: linear-gradient(
                    160deg,
                    color-mix(in srgb, var(--admin-primary) 92%, white) 0%,
                    var(--admin-primary) 42%,
                    color-mix(in srgb, var(--admin-primary) 78%, #0b1b4a) 100%
                );
            }
            @media (min-width: 1024px) {
                .admin-auth-brand {
                    display: flex;
                    flex-direction: column;
                }
            }

            .admin-auth-hex {
                background-image: url("data:image/svg+xml,%3Csvg width='120' height='104' viewBox='0 0 120 104' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M60 2 L118 33 V71 L60 102 L2 71 V33 Z' fill='none' stroke='white' stroke-opacity='0.08' stroke-width='1.5'/%3E%3C/svg%3E");
                background-size: 140px 122px;
                animation: admin-auth-pulse-soft 8s ease-in-out infinite;
            }

            .admin-auth-preview {
                animation: admin-auth-float 7s ease-in-out infinite;
                box-shadow:
                    0 30px 60px -20px rgb(0 0 0 / 0.35),
                    0 12px 24px -12px rgb(0 0 0 / 0.25);
            }

            .admin-auth-mini-card {
                animation: admin-auth-float-card 5.5s ease-in-out infinite;
                box-shadow: 0 16px 40px -16px rgb(0 0 0 / 0.35);
            }

            .admin-auth-enter {
                animation: admin-auth-fade-up 0.55s ease-out both;
            }
            .admin-auth-enter-1 { animation-delay: 0.05s; }
            .admin-auth-enter-2 { animation-delay: 0.15s; }
            .admin-auth-enter-3 { animation-delay: 0.28s; }
            .admin-auth-enter-4 { animation-delay: 0.4s; }

            .admin-auth-form-shell {
                max-width: 26rem;
            }

            .admin-auth-form-panel {
                background: #ffffff;
                color: #18181b;
            }
            .dark .admin-auth-form-panel {
                background: #09090b;
                color: #fafafa;
            }
            .admin-auth-form-panel .admin-auth-title {
                color: #18181b;
            }
            .dark .admin-auth-form-panel .admin-auth-title {
                color: #fafafa;
            }
            .admin-auth-form-panel .admin-auth-subtitle {
                color: #71717a;
            }
            .dark .admin-auth-form-panel .admin-auth-subtitle {
                color: #a1a1aa;
            }
            .admin-auth-form-panel .admin-auth-brand-link {
                color: #18181b;
            }
            .dark .admin-auth-form-panel .admin-auth-brand-link {
                color: #fafafa;
            }

            .admin-auth-input :is(input) {
                border-radius: 0.75rem !important;
                background: #f4f6fb !important;
                border-color: transparent !important;
                box-shadow: none !important;
                min-height: 3rem;
            }
            .admin-auth-input :is(input):focus {
                background: #eef2ff !important;
                outline: 2px solid color-mix(in srgb, var(--admin-primary) 35%, transparent);
                outline-offset: 0;
            }
            .dark .admin-auth-input :is(input) {
                background: rgb(255 255 255 / 0.06) !important;
                color: #f4f4f5;
            }
            .dark .admin-auth-input :is(input):focus {
                background: rgb(255 255 255 / 0.09) !important;
            }

            .admin-auth-submit {
                border-radius: 0.75rem !important;
                min-height: 3rem;
                font-weight: 600;
                color: #ffffff !important;
            }

            @media (prefers-reduced-motion: reduce) {
                .admin-auth-preview,
                .admin-auth-mini-card,
                .admin-auth-hex,
                .admin-auth-enter {
                    animation: none;
                }
            }
        </style>
    </head>
    <body class="min-h-screen bg-white antialiased dark:bg-zinc-950">
        @php
            $authTitle = setting('site_name', null, 'general')
                ?: setting('site_title', null, 'website')
                ?: setting('site_title', null, 'general')
                ?: config('app.name', 'Laravel');

            $authLogoPath = setting('admin_logo', null, 'general')
                ?: setting('logo_path', null, 'website')
                ?: setting('site_logo', null, 'general');

            $authLogoUrl = filled($authLogoPath) ? asset('storage/'.$authLogoPath) : null;
        @endphp

        <div class="relative grid min-h-dvh lg:grid-cols-2">

            {{-- Left: authentication form --}}
            <div class="admin-auth-form-panel relative flex min-h-dvh flex-col justify-center px-6 py-10 sm:px-10 lg:px-16 xl:px-24">
                <div class="absolute right-6 top-6 z-20" x-data>
                    <button
                        type="button"
                        class="flex h-9 w-9 items-center justify-center rounded-full border border-zinc-200 bg-white text-zinc-500 shadow-sm transition hover:text-zinc-800 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-400 dark:hover:text-white"
                        x-on:click="$flux.appearance = ($flux.appearance === 'dark' ? 'light' : 'dark')"
                        aria-label="{{ __('Toggle appearance') }}"
                    >
                        <flux:icon.moon variant="outline" class="size-4" x-show="$flux.appearance !== 'dark'" x-cloak />
                        <flux:icon.sun variant="outline" class="size-4" x-show="$flux.appearance === 'dark'" x-cloak />
                    </button>
                </div>

                <div class="admin-auth-form-shell admin-auth-enter admin-auth-enter-1 mx-auto w-full">
                    <a href="{{ url('/') }}" class="admin-auth-brand-link mb-10 flex justify-center" wire:navigate>
                        @if ($authLogoUrl)
                            <img
                                src="{{ $authLogoUrl }}"
                                alt="{{ $authTitle }}"
                                class="h-12 w-auto max-w-[220px] object-contain"
                            />
                        @else
                            <span class="text-center text-2xl font-bold tracking-tight">{{ $authTitle }}</span>
                        @endif
                    </a>

                    <br><br>

                    {{ $slot }}
                </div>
            </div>

            {{-- Right: brand showcase --}}
            <div class="admin-auth-brand relative min-h-dvh overflow-hidden text-white">
                <div class="admin-auth-hex pointer-events-none absolute inset-0"></div>
                <div class="pointer-events-none absolute -left-24 top-16 h-72 w-72 rounded-full bg-white/10 blur-3xl"></div>
                <div class="pointer-events-none absolute -right-16 bottom-10 h-80 w-80 rounded-full bg-black/10 blur-3xl"></div>

                {{-- Floating brand badge --}}
                {{-- <div class="admin-auth-enter admin-auth-enter-2 absolute right-10 top-10 z-20 flex h-14 min-w-14 items-center justify-center rounded-2xl bg-white/15 px-3 shadow-lg backdrop-blur-md ring-1 ring-white/30">
                    @if ($authLogoUrl)
                        <img
                            src="{{ $authLogoUrl }}"
                            alt="{{ $authTitle }}"
                            class="h-8 w-auto max-w-[96px] object-contain"
                        />
                    @else
                        <span class="text-sm font-semibold tracking-tight">{{ \Illuminate\Support\Str::limit($authTitle, 12, '') }}</span>
                    @endif
                </div> --}}

                <div class="relative z-10 flex w-full flex-1 flex-col items-center justify-center px-10 pt-16">
                    {{-- Dashboard preview --}}
                    <div class="admin-auth-enter admin-auth-enter-3 relative w-full max-w-lg">
                        <div class="admin-auth-preview relative rounded-2xl bg-white p-3 text-zinc-900">
                            <div class="flex overflow-hidden rounded-xl border border-zinc-100 bg-zinc-50">
                                {{-- Mini sidebar --}}
                                <aside class="flex w-14 shrink-0 flex-col items-center gap-3 bg-zinc-900 py-4 text-white">
                                    <span class="mb-2 flex h-8 w-8 items-center justify-center overflow-hidden rounded-lg bg-white/15">
                                        @if ($authLogoUrl)
                                            <img src="{{ $authLogoUrl }}" alt="" class="h-5 w-5 object-contain" />
                                        @else
                                            <span class="text-[11px] font-bold">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($authTitle, 0, 1)) }}</span>
                                        @endif
                                    </span>
                                    @foreach (['home', 'chart-bar', 'arrows-right-left', 'wallet', 'cog-6-tooth'] as $navIcon)
                                        <span @class([
                                            'flex h-8 w-8 items-center justify-center rounded-lg',
                                            'bg-[var(--admin-primary)] text-white' => $loop->first,
                                            'text-white/55' => ! $loop->first,
                                        ])>
                                            <flux:icon :icon="$navIcon" variant="mini" class="size-4" />
                                        </span>
                                    @endforeach
                                </aside>

                                {{-- Main content --}}
                                <div class="min-w-0 flex-1 p-4">
                                    <div class="mb-3 flex items-center justify-between gap-3">
                                        <div>
                                            <p class="text-[10px] font-medium uppercase tracking-wide text-zinc-400">{{ __('Overview') }}</p>
                                            <p class="text-sm font-semibold text-zinc-900">{{ __('Workspace dashboard') }}</p>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <span class="hidden rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-semibold text-emerald-600 sm:inline">+12.4%</span>
                                            <span class="flex h-7 w-7 items-center justify-center rounded-full bg-[color-mix(in_srgb,var(--admin-primary)_15%,white)] text-[10px] font-bold text-[var(--admin-primary)]">AF</span>
                                        </div>
                                    </div>

                                    <div class="mb-3 rounded-xl bg-white p-3 shadow-sm ring-1 ring-zinc-100">
                                        <div class="mb-2 flex items-end justify-between">
                                            <div>
                                                <p class="text-[10px] text-zinc-400">{{ __('Active sessions') }}</p>
                                                <p class="text-lg font-bold tracking-tight">12,478</p>
                                            </div>
                                            <p class="text-[10px] font-medium text-emerald-600">{{ __('Last 7 days') }}</p>
                                        </div>
                                        <svg viewBox="0 0 220 56" class="h-14 w-full" preserveAspectRatio="none" aria-hidden="true">
                                            <defs>
                                                <linearGradient id="adminAuthChartFill" x1="0" y1="0" x2="0" y2="1">
                                                    <stop offset="0%" stop-color="var(--admin-primary)" stop-opacity="0.35" />
                                                    <stop offset="100%" stop-color="var(--admin-primary)" stop-opacity="0" />
                                                </linearGradient>
                                            </defs>
                                            <path d="M0 40 C30 36, 45 18, 70 22 C95 26, 110 44, 135 30 C160 16, 180 10, 220 14 L220 56 L0 56 Z" fill="url(#adminAuthChartFill)" />
                                            <path d="M0 40 C30 36, 45 18, 70 22 C95 26, 110 44, 135 30 C160 16, 180 10, 220 14" fill="none" stroke="var(--admin-primary)" stroke-width="2.5" stroke-linecap="round" />
                                        </svg>
                                    </div>

                                    <div class="grid grid-cols-3 gap-2">
                                        @foreach ([
                                            ['label' => __('Users'), 'value' => '2.4k'],
                                            ['label' => __('Orders'), 'value' => '846'],
                                            ['label' => __('Uptime'), 'value' => '99.9%'],
                                        ] as $stat)
                                            <div class="rounded-lg bg-white p-2 shadow-sm ring-1 ring-zinc-100">
                                                <p class="text-[9px] uppercase tracking-wide text-zinc-400">{{ $stat['label'] }}</p>
                                                <p class="mt-0.5 text-sm font-semibold">{{ $stat['value'] }}</p>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Floating mini metric card --}}
                        <div class="admin-auth-mini-card absolute -left-4 bottom-6 z-20 w-40 rounded-xl bg-white p-3 text-zinc-900 ring-1 ring-black/5">
                            <div class="flex items-center justify-between gap-2">
                                <div>
                                    <p class="text-[10px] font-semibold text-zinc-500">{{ __('Growth') }}</p>
                                    <p class="text-sm font-bold">+24.8%</p>
                                </div>
                                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600">
                                    <flux:icon.arrow-trending-up variant="mini" class="size-4" />
                                </span>
                            </div>
                            <svg viewBox="0 0 100 28" class="mt-2 h-7 w-full" preserveAspectRatio="none" aria-hidden="true">
                                <path d="M0 22 C18 20, 28 8, 42 12 C56 16, 68 24, 82 10 C90 4, 96 6, 100 4" fill="none" stroke="#10b981" stroke-width="2.5" stroke-linecap="round" />
                            </svg>
                        </div>
                    </div>
                </div>

                <div class="admin-auth-enter admin-auth-enter-4 relative z-10 w-full shrink-0 px-10 pb-12 text-center">
                    <h2 class="text-2xl font-semibold tracking-tight xl:text-3xl">
                        {{ __('The easiest way to manage your workspace.') }}
                    </h2>
                    <p class="mt-2 text-sm text-white/75 xl:text-base">
                        {{ __('Everything your team needs — in one clean dashboard.') }}
                    </p>
                </div>
            </div>

        </div>
        @fluxScripts
    </body>
</html>
