<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
        <style>
            @keyframes admin-auth-fade-up {
                from { opacity: 0; transform: translateY(14px); }
                to { opacity: 1; transform: translateY(0); }
            }
            @keyframes admin-auth-kenburns {
                from { transform: scale(1.05); }
                to { transform: scale(1.12); }
            }

            .admin-auth-brand {
                display: none;
                background: #0b1b4a;
            }
            @media (min-width: 1024px) {
                .admin-auth-brand {
                    display: flex;
                    flex-direction: column;
                }
            }

            .admin-auth-slide {
                opacity: 0;
                transition: opacity 0.85s ease;
            }
            .admin-auth-slide.is-active {
                opacity: 1;
            }
            .admin-auth-slide.is-active img {
                animation: admin-auth-kenburns 8s ease-out forwards;
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
                .admin-auth-slide,
                .admin-auth-slide.is-active img {
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
                                class="h-20 w-auto max-w-[300px] object-contain"
                            />
                        @else
                            <span class="text-center text-2xl font-bold tracking-tight">{{ $authTitle }}</span>
                        @endif
                    </a>

                    <br><br>

                    {{ $slot }}
                </div>
            </div>

            {{-- Right: UM Auto plant slider --}}
            @php
                $plantSlides = [
                    [
                        'image' => asset('assets/auth/plants/manesar.jpg'),
                        'title' => __('Manesar Plant'),
                        'location' => __('IMT Manesar, Haryana'),
                        'caption' => __('Precision auto components at our Manesar facility.'),
                    ],
                    [
                        'image' => asset('assets/auth/plants/bhagwanpur.jpg'),
                        'title' => __('Bhagwanpur Plant'),
                        'location' => __('Bhagwanpur, Uttarakhand'),
                        'caption' => __('World-class manufacturing in the foothills of Uttarakhand.'),
                    ],
                    [
                        'image' => asset('assets/auth/plants/saharanpur.jpg'),
                        'title' => __('Saharanpur Plant'),
                        'location' => __('Saharanpur, Uttar Pradesh'),
                        'caption' => __('High-volume production powering India’s automotive supply chain.'),
                    ],
                ];
            @endphp

            <div
                class="admin-auth-brand relative min-h-dvh overflow-hidden text-white"
                x-data="{
                    active: 0,
                    total: {{ count($plantSlides) }},
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
                aria-label="{{ __('UM Auto plants') }}"
            >
                <div class="absolute inset-0">
                    @foreach ($plantSlides as $index => $slide)
                        <div
                            class="admin-auth-slide absolute inset-0 {{ $index === 0 ? 'is-active' : '' }}"
                            :class="{ 'is-active': active === {{ $index }} }"
                            x-bind:aria-hidden="active !== {{ $index }}"
                        >
                            <img
                                src="{{ $slide['image'] }}"
                                alt="{{ $slide['title'] }}"
                                class="h-full w-full object-cover"
                                @if ($index === 0) fetchpriority="high" @else loading="lazy" @endif
                            />
                        </div>
                    @endforeach
                </div>

                <div class="pointer-events-none absolute inset-0 bg-gradient-to-t from-black/80 via-black/25 to-black/15"></div>
                <div class="pointer-events-none absolute inset-y-0 left-0 w-24 bg-gradient-to-r from-black/20 to-transparent"></div>

                <div class="relative z-10 flex h-full min-h-dvh flex-col justify-end px-10 pb-12 pt-16">
                    <div class="admin-auth-enter admin-auth-enter-2 max-w-xl">
                        @foreach ($plantSlides as $index => $slide)
                            <div
                                @if ($index !== 0) x-cloak @endif
                                x-show="active === {{ $index }}"
                                x-transition.opacity.duration.400ms
                            >
                                <p class="mb-2 text-xs font-semibold uppercase tracking-[0.22em] text-white/70">
                                    {{ __('UM Auto plants') }}
                                </p>
                                <h2 class="text-3xl font-semibold tracking-tight xl:text-4xl">
                                    {{ $slide['title'] }}
                                </h2>
                                <p class="mt-1 text-sm font-medium text-[color-mix(in_srgb,var(--admin-primary)_55%,white)]">
                                    {{ $slide['location'] }}
                                </p>
                                <p class="mt-3 max-w-md text-sm leading-relaxed text-white/80 xl:text-base">
                                    {{ $slide['caption'] }}
                                </p>
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-8 flex items-center justify-between gap-4">
                        <div class="flex items-center gap-2" role="tablist" aria-label="{{ __('Plant slides') }}">
                            @foreach ($plantSlides as $index => $slide)
                                <button
                                    type="button"
                                    class="h-2.5 rounded-full transition-all duration-300"
                                    :class="active === {{ $index }} ? 'w-8 bg-white' : 'w-2.5 bg-white/40 hover:bg-white/70'"
                                    x-on:click="go({{ $index }})"
                                    :aria-selected="active === {{ $index }}"
                                    aria-label="{{ __('Show :plant', ['plant' => $slide['title']]) }}"
                                ></button>
                            @endforeach
                        </div>

                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                class="flex h-10 w-10 items-center justify-center rounded-full border border-white/25 bg-white/10 text-white backdrop-blur-sm transition hover:bg-white/20"
                                x-on:click="prev(); start()"
                                aria-label="{{ __('Previous plant') }}"
                            >
                                <flux:icon.chevron-left variant="mini" class="size-5" />
                            </button>
                            <button
                                type="button"
                                class="flex h-10 w-10 items-center justify-center rounded-full border border-white/25 bg-white/10 text-white backdrop-blur-sm transition hover:bg-white/20"
                                x-on:click="next(); start()"
                                aria-label="{{ __('Next plant') }}"
                            >
                                <flux:icon.chevron-right variant="mini" class="size-5" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>

        </div>
        @fluxScripts
    </body>
</html>
