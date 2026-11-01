<?php

use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('core::layouts.app')] class extends Component {
    public function with(): array
    {
        $totalUsers = User::query()->count();
        $activeUsers = User::query()->where('is_active', true)->count();
        $adminUsers = User::query()->whereIn('user_type', array_keys(User::MASTER_ROLES))->count();
        $newThisWeek = User::query()->where('created_at', '>=', now()->subWeek())->count();

        $recentUsers = User::query()
            ->latest()
            ->limit(5)
            ->get(['id', 'name', 'last_name', 'email', 'user_type', 'is_active', 'created_at']);

        $hour = (int) now()->format('G');
        $greeting = match (true) {
            $hour < 12 => __('Good morning'),
            $hour < 18 => __('Good afternoon'),
            default => __('Good evening'),
        };

        // Sample time-series for the last 7 days (deterministic-ish, no extra tables required)
        $days = collect(range(6, 0))->map(fn ($i) => now()->subDays($i));
        $userGrowthChart = [
            'labels' => $days->map(fn ($d) => $d->translatedFormat('D'))->all(),
            'datasets' => [
                [
                    'label' => __('New users'),
                    'data' => $days->map(
                        fn ($d) => User::query()->whereDate('created_at', $d->toDateString())->count()
                    )->all(),
                ],
            ],
        ];

        $inactiveUsers = max($totalUsers - $activeUsers, 0);
        $nonAdminUsers = max($totalUsers - $adminUsers, 0);
        $roleBreakdownChart = [
            'labels' => [__('Admins'), __('Regular users')],
            'datasets' => [
                ['data' => [$adminUsers, $nonAdminUsers]],
            ],
        ];

        $statusBreakdownChart = [
            'labels' => [__('Active'), __('Inactive')],
            'datasets' => [
                ['data' => [$activeUsers, $inactiveUsers]],
            ],
        ];

        return compact(
            'totalUsers',
            'activeUsers',
            'adminUsers',
            'newThisWeek',
            'recentUsers',
            'greeting',
            'userGrowthChart',
            'roleBreakdownChart',
            'statusBreakdownChart',
        );
    }
}; ?>

<div class="space-y-6">
    {{-- Hero --}}
    <section class="admin-card admin-fade-in overflow-hidden p-6 lg:p-7">
        <div class="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
            <div class="min-w-0">
                <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ now()->translatedFormat('l, F j') }}</p>
                <h1 class="mt-1 text-2xl font-semibold tracking-tight lg:text-3xl">
                    {{ $greeting }}, {{ auth()->user()->name }}
                </h1>
                <p class="mt-2 max-w-xl text-sm text-zinc-500 dark:text-zinc-400">
                    {{ __('Here’s a quick look at your workspace — :count active users online.', ['count' => max($activeUsers, 0)]) }}
                </p>
                <div class="mt-5 flex flex-wrap gap-2.5">
                    <flux:button variant="primary" icon="plus" icon:variant="outline" :href="route('admin::users.create')" wire:navigate class="!rounded-full">
                        {{ __('Add user') }}
                    </flux:button>
                    <flux:button variant="ghost" icon="users" icon:variant="outline" :href="route('admin::users.list')" wire:navigate class="!rounded-full">
                        {{ __('Manage users') }}
                    </flux:button>
                    <flux:modal.trigger name="admin-theme-panel">
                        <flux:button variant="ghost" icon="swatch" icon:variant="outline" class="!rounded-full">{{ __('Theme studio') }}</flux:button>
                    </flux:modal.trigger>
                </div>
            </div>

            <div
                class="flex shrink-0 items-center gap-4 rounded-3xl bg-[var(--admin-surface-muted)] px-5 py-4"
                x-data="{
                    hours: '{{ now()->format('H') }}',
                    minutes: '{{ now()->format('i') }}',
                    seconds: '{{ now()->format('s') }}',
                    period: '',
                    tick() {
                        const now = new Date();
                        this.hours = String(now.getHours()).padStart(2, '0');
                        this.minutes = String(now.getMinutes()).padStart(2, '0');
                        this.seconds = String(now.getSeconds()).padStart(2, '0');
                    }
                }"
                x-init="tick(); setInterval(() => tick(), 1000)"
            >
                <div class="relative flex size-16 items-center justify-center">
                    <svg class="absolute inset-0 size-16 -rotate-90" viewBox="0 0 36 36" aria-hidden="true">
                        <circle cx="18" cy="18" r="15.5" fill="none" class="stroke-zinc-200 dark:stroke-zinc-700" stroke-width="3" />
                        <circle
                            cx="18"
                            cy="18"
                            r="15.5"
                            fill="none"
                            stroke="var(--admin-primary)"
                            stroke-width="3"
                            stroke-linecap="round"
                            :stroke-dasharray="((Number(seconds) / 60) * 100) + ', 100'"
                        />
                    </svg>
                    <flux:icon.clock variant="outline" class="size-6 stroke-[1.5] text-primary" />
                </div>
                <div>
                    <p class="text-2xl font-semibold tabular-nums tracking-tight">
                        <span x-text="hours">{{ now()->format('H') }}</span>:<span x-text="minutes">{{ now()->format('i') }}</span>:<span x-text="seconds">{{ now()->format('s') }}</span>
                    </p>
                    <p class="text-xs text-zinc-500">{{ now()->translatedFormat('l') }} · {{ __('Local time') }}</p>
                </div>
            </div>
        </div>
    </section>

    {{-- KPI strip --}}
    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <div class="admin-card admin-card-hover admin-fade-in admin-fade-in-delay-1 p-5">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-sm text-zinc-500">{{ __('Total users') }}</p>
                    <p class="mt-1 text-3xl font-semibold tabular-nums">{{ number_format($totalUsers) }}</p>
                </div>
                <span class="flex size-10 items-center justify-center rounded-2xl bg-primary/15 text-primary">
                    <flux:icon.users variant="outline" class="size-5 stroke-[1.5]" />
                </span>
            </div>
            <p class="mt-3 text-xs text-zinc-500">+{{ $newThisWeek }} {{ __('this week') }}</p>
        </div>
        <div class="admin-card admin-card-hover admin-fade-in admin-fade-in-delay-2 p-5">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-sm text-zinc-500">{{ __('Active') }}</p>
                    <p class="mt-1 text-3xl font-semibold tabular-nums">{{ number_format($activeUsers) }}</p>
                </div>
                <span class="flex size-10 items-center justify-center rounded-2xl bg-secondary/15 text-secondary">
                    <flux:icon.check-circle variant="outline" class="size-5 stroke-[1.5]" />
                </span>
            </div>
            <p class="mt-3 text-xs text-zinc-500">{{ __('Currently enabled accounts') }}</p>
        </div>
        <div class="admin-card admin-card-hover admin-fade-in admin-fade-in-delay-3 p-5">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-sm text-zinc-500">{{ __('Admins') }}</p>
                    <p class="mt-1 text-3xl font-semibold tabular-nums">{{ number_format($adminUsers) }}</p>
                </div>
                <span class="flex size-10 items-center justify-center rounded-2xl bg-primary/15 text-primary">
                    <flux:icon.shield-check variant="outline" class="size-5 stroke-[1.5]" />
                </span>
            </div>
            <p class="mt-3 text-xs text-zinc-500">{{ __('Admin · sub-admin · staff') }}</p>
        </div>
    </section>

    {{-- Charts --}}
    <div class="grid gap-4 xl:grid-cols-3">
        <section class="admin-card admin-fade-in admin-fade-in-delay-1 p-5 xl:col-span-2">
            <div class="mb-3">
                <flux:heading size="lg">{{ __('New users (7 days)') }}</flux:heading>
                <flux:subheading>{{ __('Daily signups over the last week.') }}</flux:subheading>
            </div>
            <x-core::charts.chart type="line" :dataset="$userGrowthChart" :height="260" />
        </section>

        <section class="admin-card admin-fade-in admin-fade-in-delay-2 p-5">
            <div class="mb-3">
                <flux:heading size="lg">{{ __('Users by role') }}</flux:heading>
                <flux:subheading>{{ __('Admins vs regular users.') }}</flux:subheading>
            </div>
            <x-core::charts.chart type="doughnut" :dataset="$roleBreakdownChart" :height="260" />
        </section>
    </div>

    <div class="grid gap-4 xl:grid-cols-3">
        <section class="admin-card admin-fade-in admin-fade-in-delay-3 p-5 xl:col-span-2">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <flux:heading size="lg">{{ __('Recent users') }}</flux:heading>
                    <flux:subheading>{{ __('Latest accounts in the system.') }}</flux:subheading>
                </div>
                <flux:button size="sm" variant="ghost" :href="route('admin::users.list')" wire:navigate>
                    {{ __('View all') }}
                </flux:button>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                    <thead class="bg-[var(--admin-surface-muted)]">
                        <tr>
                            <th class="px-3 py-3 text-left text-xs font-semibold text-zinc-500">{{ __('Name') }}</th>
                            <th class="px-3 py-3 text-left text-xs font-semibold text-zinc-500">{{ __('Email') }}</th>
                            <th class="px-3 py-3 text-left text-xs font-semibold text-zinc-500">{{ __('Role') }}</th>
                            <th class="px-3 py-3 text-left text-xs font-semibold text-zinc-500">{{ __('Status') }}</th>
                            <th class="px-3 py-3 text-right text-xs font-semibold text-zinc-500">{{ __('Joined') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        @forelse ($recentUsers as $user)
                            <tr class="transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-800/40">
                                <td class="px-3 py-3">
                                    <div class="flex items-center gap-3">
                                        <span class="flex size-8 items-center justify-center rounded-full bg-primary/15 text-xs font-semibold text-primary">
                                            {{ $user->initials() }}
                                        </span>
                                        <span class="text-sm font-medium">{{ $user->name }} {{ $user->last_name }}</span>
                                    </div>
                                </td>
                                <td class="px-3 py-3 text-sm text-zinc-600 dark:text-zinc-300">{{ $user->email }}</td>
                                <td class="px-3 py-3 text-sm capitalize">{{ str_replace('_', ' ', $user->user_type ?? 'user') }}</td>
                                <td class="px-3 py-3">
                                    @if ($user->is_active)
                                        <span class="inline-flex rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-700 dark:bg-green-900/40 dark:text-green-300">{{ __('Active') }}</span>
                                    @else
                                        <span class="inline-flex rounded-full bg-zinc-100 px-2.5 py-0.5 text-xs font-medium text-zinc-500 dark:bg-zinc-700 dark:text-zinc-300">{{ __('Inactive') }}</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3 text-right text-sm text-zinc-500">{{ $user->created_at?->diffForHumans() }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-3 py-10 text-center text-sm text-zinc-500">{{ __('No users yet.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="admin-card admin-fade-in admin-fade-in-delay-4 p-5">
            <div class="mb-3">
                <flux:heading size="lg">{{ __('Account status') }}</flux:heading>
                <flux:subheading>{{ __('Active vs inactive accounts.') }}</flux:subheading>
            </div>
            <x-core::charts.chart type="doughnut" :dataset="$statusBreakdownChart" :height="240" />
        </section>
    </div>
</div>
