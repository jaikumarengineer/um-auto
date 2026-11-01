<!DOCTYPE html>
<html
    lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    data-auth-user=""
    data-auth-home="{{ route('admin::main', absolute: false) }}"
    data-auth-login="{{ route('admin::login', absolute: false) }}"
>
    <head>
        @include('partials.head')
        <style>
            @keyframes admin-auth-fade-up {
                from { opacity: 0; transform: translateY(14px); }
                to { opacity: 1; transform: translateY(0); }
            }
            @keyframes admin-auth-float {
                0%, 100% { transform: translateY(0); }
                50% { transform: translateY(-10px); }
            }

            .admin-auth-brand {
                display: none;
                background:
                    radial-gradient(circle at 18% 20%, rgb(255 255 255 / 0.18), transparent 34%),
                    radial-gradient(circle at 82% 78%, rgb(255 255 255 / 0.12), transparent 28%),
                    linear-gradient(160deg, #0f766e 0%, #0d9488 42%, #14b8a6 100%);
            }
            @media (min-width: 1024px) {
                .admin-auth-brand {
                    display: flex;
                    flex-direction: column;
                }
            }

            .admin-auth-brand-grid {
                background-image:
                    linear-gradient(rgb(255 255 255 / 0.08) 1px, transparent 1px),
                    linear-gradient(90deg, rgb(255 255 255 / 0.08) 1px, transparent 1px);
                background-size: 42px 42px;
                mask-image: radial-gradient(circle at center, black 35%, transparent 85%);
            }

            .admin-auth-slide {
                opacity: 0;
                pointer-events: none;
                transition: opacity 0.7s ease;
            }
            .admin-auth-slide.is-active {
                opacity: 1;
                pointer-events: auto;
            }
            .admin-auth-slide-card {
                animation: admin-auth-float 7s ease-in-out infinite;
            }

            .admin-auth-enter {
                animation: admin-auth-fade-up 0.55s ease-out both;
            }
            .admin-auth-enter-1 { animation-delay: 0.05s; }
            .admin-auth-enter-2 { animation-delay: 0.15s; }

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
            .admin-auth-input :is(input)[data-invalid],
            .admin-auth-input :is(input)[aria-invalid="true"] {
                border-color: #ef4444 !important;
                background: #fef2f2 !important;
                outline: 2px solid color-mix(in srgb, #ef4444 35%, transparent);
                outline-offset: 0;
            }
            .admin-auth-input [data-flux-error] {
                margin-top: 0.5rem;
            }
            .dark .admin-auth-input :is(input) {
                background: rgb(255 255 255 / 0.06) !important;
                color: #f4f4f5;
            }
            .dark .admin-auth-input :is(input):focus {
                background: rgb(255 255 255 / 0.09) !important;
            }
            .dark .admin-auth-input :is(input)[data-invalid],
            .dark .admin-auth-input :is(input)[aria-invalid="true"] {
                border-color: #f87171 !important;
                background: rgb(239 68 68 / 0.12) !important;
            }

            .admin-auth-submit {
                border-radius: 0.75rem !important;
                min-height: 3rem;
                font-weight: 600;
                color: #ffffff !important;
            }

            @media (prefers-reduced-motion: reduce) {
                .admin-auth-enter,
                .admin-auth-slide-card {
                    animation: none;
                    transition: none;
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

            $authLogoUrl = media_url($authLogoPath);
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
                                class="h-20 w-auto max-w-[300px] object-contain"
                            />
                        @else
                            <span class="text-center text-2xl font-bold tracking-tight">{{ $authTitle }}</span>
                        @endif
                    </a>

                    {{ $slot }}
                </div>
            </div>

            {{-- Right: quality workspace dashboard showcase --}}
            @php
                $dashboardSlides = [
                    [
                        'file' => 'assets/auth/dashboard/quality.jpg',
                        'alt' => __('Quality overview dashboard'),
                        'title' => __('Quality overview'),
                        'caption' => __('Track inspections, pass rates, and open reports in one place.'),
                    ],
                    [
                        'file' => 'assets/auth/dashboard/incoming.jpg',
                        'alt' => __('Incoming inspection dashboard'),
                        'title' => __('Incoming inspection'),
                        'caption' => __('Monitor lots, vendors, sampling plans, and acceptance status.'),
                    ],
                    [
                        'file' => 'assets/auth/dashboard/purchase.jpg',
                        'alt' => __('Purchase workspace dashboard'),
                        'title' => __('Purchase workspace'),
                        'caption' => __('Follow orders, invoices, and on-time delivery at a glance.'),
                    ],
                ];

                $dashboardSlides = array_map(function (array $slide) {
                    $path = public_path($slide['file']);
                    $slide['src'] = asset($slide['file']).'?v='.(is_file($path) ? filemtime($path) : time());

                    return $slide;
                }, $dashboardSlides);
            @endphp

            <div
                class="admin-auth-brand relative min-h-dvh overflow-hidden text-white"
                x-data="{
                    active: 0,
                    total: {{ count($dashboardSlides) }},
                    timer: null,
                    start() {
                        this.stop();
                        this.timer = setInterval(() => { this.next(); }, 5500);
                    },
                    stop() {
                        if (this.timer) clearInterval(this.timer);
                    },
                    next() {
                        this.active = (this.active + 1) % this.total;
                    },
                    prev() {
                        this.active = (this.active - 1 + this.total) % this.total;
                    },
                    go(index) {
                        this.active = index;
                        this.start();
                    }
                }"
                x-init="start()"
                x-on:mouseenter="stop()"
                x-on:mouseleave="start()"
                role="region"
                aria-roledescription="carousel"
                aria-label="{{ __('Workspace gallery') }}"
            >
                <div class="admin-auth-brand-grid pointer-events-none absolute inset-0"></div>

                <div class="relative z-10 flex h-full min-h-dvh flex-col px-8 py-10 xl:px-14">
                    <div class="flex flex-1 flex-col items-center justify-center">
                        <div class="relative w-full max-w-[30rem]">
                            @foreach ($dashboardSlides as $index => $slide)
                                <div
                                    class="admin-auth-slide {{ $index === 0 ? 'relative is-active' : 'absolute inset-0' }}"
                                    :class="{ 'is-active': active === {{ $index }}, 'relative': active === {{ $index }}, 'absolute inset-0': active !== {{ $index }} }"
                                    x-bind:aria-hidden="active !== {{ $index }}"
                                >
                                    <div class="admin-auth-slide-card overflow-hidden rounded-[1.75rem] shadow-[0_28px_70px_rgb(0_0_0_/_0.28)] ring-1 ring-white/30">
                                        <img
                                            src="{{ $slide['src'] }}"
                                            alt="{{ $slide['alt'] }}"
                                            class="aspect-[4/5] w-full object-cover object-[center_18%]"
                                            @if ($index === 0) fetchpriority="high" @else loading="lazy" @endif
                                        />
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="mt-8 space-y-6">
                        <div class="max-w-md">
                            <p class="text-2xl font-semibold tracking-tight text-white xl:text-3xl">
                                {{ __('The easiest way to manage your quality workspace.') }}
                            </p>
                            <p class="mt-2 text-sm text-white/80 xl:text-base">
                                <span x-text="[
                                    @foreach ($dashboardSlides as $slide)
                                        @js($slide['caption']),
                                    @endforeach
                                ][active]"></span>
                            </p>
                        </div>

                        <div class="flex items-center justify-between gap-4">
                            <div class="flex items-center gap-2" role="tablist" aria-label="{{ __('Workspace slides') }}">
                                @foreach ($dashboardSlides as $index => $slide)
                                    <button
                                        type="button"
                                        class="h-2.5 rounded-full transition-all duration-300"
                                        :class="active === {{ $index }} ? 'w-8 bg-white' : 'w-2.5 bg-white/40 hover:bg-white/70'"
                                        x-on:click="go({{ $index }})"
                                        :aria-selected="active === {{ $index }}"
                                        aria-label="{{ __('Show slide :number', ['number' => $index + 1]) }}"
                                    ></button>
                                @endforeach
                            </div>

                            <div class="flex items-center gap-2">
                                <button
                                    type="button"
                                    class="flex h-10 w-10 items-center justify-center rounded-full border border-white/25 bg-white/10 text-white backdrop-blur-sm transition hover:bg-white/20"
                                    x-on:click="prev(); start()"
                                    aria-label="{{ __('Previous slide') }}"
                                >
                                    <flux:icon.chevron-left variant="mini" class="size-5" />
                                </button>
                                <button
                                    type="button"
                                    class="flex h-10 w-10 items-center justify-center rounded-full border border-white/25 bg-white/10 text-white backdrop-blur-sm transition hover:bg-white/20"
                                    x-on:click="next(); start()"
                                    aria-label="{{ __('Next slide') }}"
                                >
                                    <flux:icon.chevron-right variant="mini" class="size-5" />
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
        @fluxScripts
    </body>
</html>
